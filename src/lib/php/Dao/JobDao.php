<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Monolog\Logger;

class JobDao
{
  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;

  function __construct(DbManager $dbManager, Logger $logger)
  {
    $this->dbManager = $dbManager;
    $this->logger = $logger;
  }

  public function getAllJobStatus($uploadId, $userId, $groupId)
  {
    $result = array();
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt,
      "SELECT jobqueue.jq_pk as jq_pk,
              jobqueue.jq_end_bits as end_bits
       FROM jobqueue
         INNER JOIN job
           ON jobqueue.jq_job_fk = job.job_pk
         LEFT JOIN group_user_member gm
           ON gm.user_fk = job_user_fk
       WHERE job.job_upload_fk = $1
         AND (job_user_fk = $2
              OR gm.group_fk = $3)");

    $res = $this->dbManager->execute($stmt, array($uploadId, $userId, $groupId));
    while ($row = $this->dbManager->fetchArray($res)) {
      $result[$row['jq_pk']] = $row['end_bits'];
    }
    $this->dbManager->freeResult($res);

    return $result;
  }

  public function getChlidJobStatus($jobId)
  {
    $result = array();
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt,
      "SELECT jobqueue.jq_pk as jq_pk,
              jobqueue.jq_end_bits as end_bits
      FROM jobqueue
      WHERE jq_job_fk = $1");

    $res = $this->dbManager->execute($stmt, array($jobId));
    while ($row = $this->dbManager->fetchArray($res)) {
      $result[$row['jq_pk']] = $row['end_bits'];
    }
    $this->dbManager->freeResult($res);

    return $result;
  }

  public function hasActionPermissionsOnJob($jobId, $userId, $groupId)
  {
    $result = array();
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt,
      "SELECT *
       FROM job
         LEFT JOIN group_user_member gm
           ON gm.user_fk = job_user_fk
       WHERE job_pk = $1
         AND (job_user_fk = $2
              OR gm.group_fk = $3)");

    $res = $this->dbManager->execute($stmt, array($jobId, $userId, $groupId));
    while ($row = $this->dbManager->fetchArray($res)) {
      $result[$row['jq_pk']] = $row['end_bits'];
    }
    $this->dbManager->freeResult($res);

    return $result;
  }

  /**
   * @brief Check if a user may pause, restart or cancel a job queue entry.
   *
   * Unlike hasActionPermissionsOnJob(), which expects a job_pk, this expects
   * the jobqueue primary key (jq_pk), which is what the "showjobs" page and
   * the scheduler use to identify a single step of a job.
   *
   * The entry has to belong to a job owned by the user or by a member of the
   * user's active group. If an upload id is given, the job also has to belong
   * to that upload.
   *
   * @param int      $jobQueueId jq_pk of the job queue entry
   * @param int      $userId     Id of the user performing the action
   * @param int      $groupId    Active group id of the user
   * @param int|null $uploadId   Upload the job has to belong to (null, 0 or -1 to skip)
   * @return bool True if the user may act on the job queue entry
   */
  public function hasActionPermissionsOnJobQueue($jobQueueId, $userId, $groupId, $uploadId = null)
  {
    if (empty($jobQueueId)) {
      return false;
    }
    $sql = "SELECT jobqueue.jq_pk
            FROM jobqueue
              INNER JOIN job
                ON jobqueue.jq_job_fk = job.job_pk
              LEFT JOIN group_user_member gm
                ON gm.user_fk = job.job_user_fk
            WHERE jobqueue.jq_pk = $1
              AND (job.job_user_fk = $2
                   OR gm.group_fk = $3)";
    $params = array($jobQueueId, $userId, $groupId);
    $stmt = __METHOD__;
    if (!empty($uploadId) && $uploadId > 0) {
      $sql .= " AND job.job_upload_fk = $4";
      $params[] = $uploadId;
      $stmt .= '.upload';
    }
    $row = $this->dbManager->getSingleRow($sql, $params, $stmt);
    return !empty($row);
  }
}
