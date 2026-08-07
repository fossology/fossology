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

  /**
   * Check if user has permission to perform actions on a job.
   *
   * @param int $jobId  The job_pk from the job table
   * @param int $userId
   * @param int $groupId
   * @return bool True if user has permission, false otherwise
   */
  public function hasActionPermissionsOnJob($jobId, $userId, $groupId)
  {
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt,
      "SELECT 1
       FROM job
         LEFT JOIN group_user_member gm
           ON gm.user_fk = job_user_fk
       WHERE job_pk = $1
         AND (job_user_fk = $2
              OR gm.group_fk = $3)");

    $res = $this->dbManager->execute($stmt, array($jobId, $userId, $groupId));
    $row = $this->dbManager->fetchArray($res);
    $this->dbManager->freeResult($res);

    return !empty($row);
  }

  /**
   * Check if user has permission to perform actions on a job queue item.
   *
   * @param int $jqPk   The jq_pk from the jobqueue table
   * @param int $userId
   * @param int $groupId
   * @return bool True if user has permission, false otherwise
   */
  public function hasActionPermissionsOnJobQueue($jqPk, $userId, $groupId)
  {
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt,
      "SELECT 1
       FROM jobqueue jq
         INNER JOIN job j
           ON jq.jq_job_fk = j.job_pk
         LEFT JOIN group_user_member gm
           ON gm.user_fk = j.job_user_fk
       WHERE jq.jq_pk = $1
         AND (j.job_user_fk = $2
              OR gm.group_fk = $3)");

    $res = $this->dbManager->execute($stmt, array($jqPk, $userId, $groupId));
    $row = $this->dbManager->fetchArray($res);
    $this->dbManager->freeResult($res);

    return !empty($row);
  }
}
