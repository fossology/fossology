<?php
/*
 SPDX-FileCopyrightText: © 2026 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use Exception;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class MultiUploadAggregatorScheduler
 * @brief Schedule N single-upload report jobs + a dependent merge job.
 *
 * Uploads whose report of the requested format is already present and still
 * valid are not scheduled at all; the merge picks their existing report up
 * from the reportgen table.
 *
 * Uses JobQueueAdd directly for report agents so multiple instances of the
 * same agent type can share one job_pk (AgentAdd's IsAlreadyScheduled would
 * otherwise collapse them).
 */
class MultiUploadAggregatorScheduler
{
  /**
   * Schedule generate-then-merge for a library-supported format.
   *
   * @param string $format Library format (spdx2tv, cyclonedx, …)
   * @param Upload $primaryUpload Primary upload (job_upload_fk / download anchor)
   * @param Upload[] $allUploads All uploads including primary (keyed by id or list)
   * @param int $userId
   * @param int $groupId
   * @return array{0:int,1:int} [jobId, mergeJqPk]
   * @throws Exception
   */
  public function schedule($format, Upload $primaryUpload, array $allUploads, $userId, $groupId)
  {
    if (!ReportUtils::isAggregatorSupportedFormat($format)) {
      throw new Exception("Format '$format' is not supported by report aggregator");
    }

    $pluginName = ReportUtils::agentPluginNameForAggregatorFormat($format);
    /** @var AgentPlugin|null $reportAgent */
    $reportAgent = plugin_find($pluginName);
    if ($reportAgent === null) {
      throw new Exception("Cannot find report agent plugin $pluginName");
    }

    $uploads = [];
    foreach ($allUploads as $key => $upload) {
      if ($upload instanceof Upload) {
        $uploads[$upload->getId()] = $upload;
      }
    }
    $primaryId = $primaryUpload->getId();
    if (!isset($uploads[$primaryId])) {
      $uploads[$primaryId] = $primaryUpload;
    }
    if (count($uploads) < 2) {
      throw new Exception("Aggregator multi-upload schedule requires at least two uploads");
    }

    // Primary first: the merge resolves conflicts in favour of the first input.
    $uploadIds = array_merge([$primaryId],
      array_diff(array_keys($uploads), [$primaryId]));

    $reportReuse = new ReportReuse();
    $reusedIds = [];
    foreach ($uploadIds as $uploadId) {
      if ($reportReuse->findReusableReport($uploadId, $format, $groupId) !== null) {
        $reusedIds[] = $uploadId;
      }
    }

    $jobName = "Multi File " . $format;
    $jobId = \JobAddJob($userId, $groupId, $jobName, $primaryId);
    $error = "";
    $reportJqPks = [];

    foreach (array_diff($uploadIds, $reusedIds) as $uploadId) {
      $deps = [];
      $adjPlugin = plugin_find('agent_adj2nest');
      if ($adjPlugin) {
        $adjJq = $adjPlugin->AgentAdd($jobId, $uploadId, $error, [], null);
        if ($adjJq < 0) {
          throw new Exception(_("Cannot schedule") . ": " . $error);
        }
        if (!empty($adjJq)) {
          $deps[] = $adjJq;
        }
      }

      // Bypass AgentAdd/IsAlreadyScheduled so N same-type agents fit one job.
      $jqPk = \JobQueueAdd($jobId, $reportAgent->AgentName, $uploadId, "", $deps);
      if (empty($jqPk)) {
        throw new Exception(_("Cannot schedule") . ": failed to queue "
          . $reportAgent->AgentName . " for upload $uploadId");
      }
      $reportJqPks[] = $jqPk;
    }

    // Comma joined on purpose: the scheduler splits jq_cmd_args on spaces into
    // a fixed size argv, so one flag per upload would overflow it.
    $mergeCmdArgs = '--format=' . $format
      . ' --uploads=' . implode(',', $uploadIds);
    if (!empty($reusedIds)) {
      $mergeCmdArgs .= ' --reused=' . implode(',', $reusedIds);
    }
    $mergeJqPk = \JobQueueAdd($jobId, 'reportaggregator', $primaryId, "",
      $reportJqPks, null, $mergeCmdArgs);
    if (empty($mergeJqPk)) {
      throw new Exception(_("Cannot schedule") . ": failed to queue reportaggregator");
    }

    $schedulerError = "";
    $output = "";
    if (!\fo_communicate_with_scheduler("database", $output, $schedulerError)) {
      throw new Exception(_("Cannot schedule") . ": " . $schedulerError . "\n" . $output);
    }

    return [$jobId, $mergeJqPk];
  }

  /**
   * Convenience: schedule using current Auth user/group.
   *
   * @param string $format
   * @param Upload $primaryUpload
   * @param Upload[] $allUploads
   * @return array{0:int,1:int}
   * @throws Exception
   */
  public function scheduleForCurrentUser($format, Upload $primaryUpload, array $allUploads)
  {
    return $this->schedule($format, $primaryUpload, $allUploads,
      Auth::getUserId(), Auth::getGroupId());
  }
}
