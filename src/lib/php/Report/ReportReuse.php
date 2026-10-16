<?php
/*
 SPDX-FileCopyrightText: © 2026 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;

/**
 * @class ReportReuse
 * @brief Decide whether an upload's report can be reused instead of regenerated.
 *
 * Report file names are deterministic (`<FORMAT>_<packagename><ext>`), so a
 * report is looked up by the exact path the agent would write, cross-checked
 * against the reportgen table for ownership and recency.
 */
class ReportReuse
{
  /** @var DbManager $dbManager */
  private $dbManager;

  /** @var UploadDao $uploadDao */
  private $uploadDao;

  /**
   * @param DbManager|null $dbManager Defaults to the container's db.manager
   * @param UploadDao|null $uploadDao Defaults to the container's dao.upload
   */
  function __construct(DbManager $dbManager = null, UploadDao $uploadDao = null)
  {
    global $container;

    $this->dbManager = $dbManager ?: $container->get('db.manager');
    $this->uploadDao = $uploadDao ?: $container->get('dao.upload');
  }

  /**
   * @brief Path of this upload's report of the given format, if it is usable.
   *
   * Usable means the group generated it, nothing else has written to that path
   * since, and the file is still there. It says nothing about whether the
   * report is up to date - see findReusableReport() for that.
   *
   * @param int    $uploadId Upload to look up
   * @param string $format   Report output format (spdx2tv, cyclonedx, …)
   * @param int    $groupId  Group the report must have been generated for
   * @param string &$reason  Set to why the report was rejected, for logging
   * @return string|null Absolute report path, or null when there is none
   */
  public function resolveReportPath($uploadId, $format, $groupId, &$reason = null)
  {
    $upload = $this->uploadDao->getUpload($uploadId);
    if ($upload === null) {
      $reason = "upload not found";
      return null;
    }
    $path = ReportUtils::canonicalReportPath($format, $upload->getFilename());

    $sql = "SELECT r.job_fk FROM reportgen r " .
      "INNER JOIN job j ON j.job_pk = r.job_fk " .
      "WHERE r.upload_fk = $1 AND r.filepath = $2 AND j.job_group_fk = $3 " .
      "ORDER BY r.job_fk DESC LIMIT 1";
    $row = $this->dbManager->getSingleRow($sql, [$uploadId, $path, $groupId],
      __METHOD__ . '.registered');
    if (empty($row)) {
      $reason = "no $format report generated for this group yet";
      return null;
    }

    // Report names carry no timestamp, so uploads sharing a file name share a
    // report path. job_fk is a serial, so a higher one wrote the file later;
    // an equal one belonging to another upload is an undecidable tie.
    $otherWriter = $this->dbManager->getSingleRow(
      "SELECT 1 FROM reportgen WHERE filepath = $1 " .
      "AND (job_fk > $2 OR (job_fk = $2 AND upload_fk <> $3)) LIMIT 1",
      [$path, $row['job_fk'], $uploadId], __METHOD__ . '.otherWriter');
    if (!empty($otherWriter)) {
      $reason = "report file was last written for a different upload";
      return null;
    }

    if (!is_file($path)) {
      $reason = "report file is missing";
      return null;
    }

    return $path;
  }

  /**
   * @brief Path of a report that can be reused instead of being regenerated.
   *
   * On top of resolveReportPath(), the report must be newer than the last
   * change to the data it is built from.
   *
   * @param int    $uploadId Upload to look up
   * @param string $format   Report output format (spdx2tv, cyclonedx, …)
   * @param int    $groupId  Group the report must have been generated for
   * @param string &$reason  Set to why reuse was rejected, for logging
   * @return string|null Absolute report path, or null when it must be generated
   */
  public function findReusableReport($uploadId, $format, $groupId, &$reason = null)
  {
    $path = $this->resolveReportPath($uploadId, $format, $groupId, $reason);
    if ($path === null) {
      return null;
    }

    $reportTime = filemtime($path);
    if ($reportTime === false ||
        $reportTime < $this->latestUploadChange($uploadId, $groupId)) {
      $reason = "report is older than the latest change to the upload";
      return null;
    }

    return $path;
  }

  /**
   * @brief Time of the last change to the data a report is built from.
   *
   * Covers this group's clearing decisions and events, and any completed job
   * on the upload other than a report generation (which necessarily ends after
   * the report file it wrote).
   *
   * @param int $uploadId
   * @param int $groupId
   * @return int Unix timestamp, 0 when nothing was ever recorded
   */
  public function latestUploadChange($uploadId, $groupId)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $reportTypes = "'" . implode("','", ReportUtils::REPORT_AGENT_TYPES) . "'";

    $sql = "SELECT EXTRACT(EPOCH FROM GREATEST(
        (SELECT max(cd.date_added) FROM clearing_decision cd
           INNER JOIN $uploadTreeTableName ut ON ut.uploadtree_pk = cd.uploadtree_fk
          WHERE ut.upload_fk = $1 AND cd.group_fk = $2),
        (SELECT max(ce.date_added) FROM clearing_event ce
           INNER JOIN $uploadTreeTableName ut ON ut.uploadtree_pk = ce.uploadtree_fk
          WHERE ut.upload_fk = $1 AND ce.group_fk = $2),
        (SELECT max(jq.jq_endtime) FROM jobqueue jq
           INNER JOIN job j ON j.job_pk = jq.jq_job_fk
          WHERE j.job_upload_fk = $1 AND jq.jq_endtime IS NOT NULL
            AND jq.jq_type NOT IN ($reportTypes))
      )) AS ts";
    $row = $this->dbManager->getSingleRow($sql, [$uploadId, $groupId],
      __METHOD__ . ".$uploadTreeTableName");

    return empty($row['ts']) ? 0 : intval($row['ts']);
  }
}
