<?php
/*
 SPDX-FileCopyrightText: © 2026 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Merge the per-upload reports of a multi-upload request using the
 *        report-aggregator Python library.
 */

namespace Fossology\ReportAggregator;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Report\ReportReuse;
use Fossology\Lib\Report\ReportUtils;

include_once(__DIR__ . "/version.php");

/**
 * @class ReportAggregatorAgent
 * @brief Collects each upload's report, merges them, registers outputs.
 */
class ReportAggregatorAgent extends Agent
{
  const FORMAT_KEY = "format";
  const UPLOADS_KEY = "uploads";
  const REUSED_KEY = "reused";

  /** @var UploadDao */
  private $uploadDao;

  /** @var ReportUtils */
  private $reportutils;

  /** @var ReportReuse */
  private $reportReuse;

  /** @var string */
  private $format = "";

  function __construct()
  {
    parent::__construct(REPORTAGGREGATOR_AGENT_NAME, AGENT_VERSION, AGENT_REV);

    $this->uploadDao = $this->container->get('dao.upload');
    $this->reportutils = new ReportUtils();
    $this->reportReuse = new ReportReuse();
    $this->agentSpecifLongOptions[] = self::FORMAT_KEY . ':';
    $this->agentSpecifLongOptions[] = self::UPLOADS_KEY . ':';
    $this->agentSpecifLongOptions[] = self::REUSED_KEY . ':';
  }

  /**
   * @brief Parse a comma separated list of upload ids from the arguments
   * @param string $key Argument name
   * @return int[]
   */
  private function getIdListArg($key)
  {
    if (!array_key_exists($key, $this->args)) {
      return [];
    }
    $ids = [];
    foreach (explode(',', $this->args[$key]) as $id) {
      $id = intval(trim($id));
      if ($id > 0) {
        $ids[] = $id;
      }
    }
    return $ids;
  }

  /**
   * @brief Stage each upload's report as <tempDir>/<uploadId><ext>
   *
   * The merge library derives the provenance source id from the input file
   * stem, so inputs are staged under their upload id rather than merged from
   * their canonical location.
   *
   * @param int[] $uploadIds Uploads to merge, in merge order
   * @param int[] $reusedIds Uploads whose report was not regenerated
   * @param string $tempDir  Staging directory
   * @return string[]|false Staged paths in merge order, false on failure
   */
  private function stageInputs($uploadIds, $reusedIds, $tempDir)
  {
    $inputPaths = [];

    foreach ($uploadIds as $uploadId) {
      $reason = "";
      $source = $this->reportReuse->resolveReportPath($uploadId, $this->format,
        $this->groupId, $reason);
      $upload = $this->uploadDao->getUpload($uploadId);
      $name = $upload === null ? "?" : $upload->getFilename();

      if ($source === null) {
        echo "ERROR: no $this->format report available for upload $uploadId " .
          "($name): $reason\n";
        return false;
      }

      $target = ReportUtils::getAggregatorTempFilePath($this->jobId, $uploadId,
        $this->format);
      if (!copy($source, $target)) {
        echo "ERROR: cannot stage report for upload $uploadId ($name): " .
          "$source -> $target\n";
        return false;
      }

      $state = in_array($uploadId, $reusedIds) ? "reused" : "regenerated";
      echo "upload $uploadId ($name): $state $source\n";
      $inputPaths[] = $target;
      $this->heartbeat(0);
    }

    return $inputPaths;
  }

  /**
   * @copydoc Fossology::Lib::Agent::Agent::processUploadId()
   * @see Fossology::Lib::Agent::Agent::processUploadId()
   */
  function processUploadId($uploadId)
  {
    global $SysConf;

    if (array_key_exists(self::FORMAT_KEY, $this->args)) {
      $this->format = trim($this->args[self::FORMAT_KEY]);
    }
    if (empty($this->format)) {
      $this->bail(1);
      return false;
    }

    $uploadIds = $this->getIdListArg(self::UPLOADS_KEY);
    if (count($uploadIds) < 1) {
      echo "ERROR: no uploads given to merge\n";
      $this->bail(1);
      return false;
    }

    $tempDir = ReportUtils::getAggregatorTempDir($this->jobId);
    if (!is_dir($tempDir) && !mkdir($tempDir, 0777, true)) {
      echo "ERROR: cannot create staging directory: $tempDir\n";
      $this->bail(1);
      return false;
    }

    $inputPaths = $this->stageInputs($uploadIds,
      $this->getIdListArg(self::REUSED_KEY), $tempDir);
    if ($inputPaths === false) {
      // Keep staged files for debugging on failure.
      $this->bail(1);
      return false;
    }

    $ext = ReportUtils::extensionForAggregatorFormat($this->format);
    $reportDir = $SysConf['FOSSOLOGY']['path'] . "/report/";
    if (!is_dir($reportDir)) {
      mkdir($reportDir, 0777, true);
    }
    $mergedPath = $reportDir . "aggregated_" . $this->format . "_" . $this->jobId . $ext;

    $projectUser = $SysConf['DIRECTORIES']['PROJECTUSER'] ?? 'fossy';
    $pythonDeps = "/home/" . $projectUser . "/pythondeps";
    $cmd = 'PYTHONPATH=' . escapeshellarg($pythonDeps)
      . ' python3 -m report_aggregator merge';
    foreach ($inputPaths as $path) {
      $cmd .= ' ' . escapeshellarg($path);
    }
    $cmd .= ' -o ' . escapeshellarg($mergedPath)
      . ' --format ' . escapeshellarg($this->format)
      . ' --json 2>&1';

    $this->heartbeat(0);
    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);
    $stdout = implode("\n", $output);
    echo $stdout . "\n";

    if ($exitCode !== 0) {
      echo "ERROR: report-aggregator merge failed (exit $exitCode)\n";
      // Keep temps for debugging on failure.
      $this->bail(1);
      return false;
    }

    $provenancePath = preg_replace('/(\.[^.]+)$/', '.provenance.json', $mergedPath);
    if ($provenancePath === $mergedPath) {
      $provenancePath = $mergedPath . '.provenance.json';
    }
    // Library writes <stem>.provenance.json beside the output.
    $stemProvenance = pathinfo($mergedPath, PATHINFO_DIRNAME) . '/'
      . pathinfo($mergedPath, PATHINFO_FILENAME) . '.provenance.json';
    if (is_file($stemProvenance)) {
      $provenancePath = $stemProvenance;
    }

    if (!is_file($mergedPath)) {
      echo "ERROR: merged report not written: $mergedPath\n";
      $this->bail(1);
      return false;
    }

    $this->reportutils->updateOrInsertReportgenEntry($uploadId, $this->jobId, $mergedPath);
    if (is_file($provenancePath)) {
      $this->reportutils->updateOrInsertReportgenEntry($uploadId, $this->jobId, $provenancePath);
    }

    ReportUtils::removeAggregatorTempDir($this->jobId);
    $this->heartbeat(count($inputPaths));
    return true;
  }
}

$agent = new ReportAggregatorAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
