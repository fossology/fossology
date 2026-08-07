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

  public function getJobIdFromJobQueue($jqPk)
  {
    $stmt = __METHOD__;
    $row = $this->dbManager->getSingleRow(
      "SELECT jq_job_fk FROM jobqueue WHERE jq_pk = $1",
      array($jqPk),
      $stmt
    );
    return $row ? $row['jq_job_fk'] : null;
  }

  public function getJobDependencies($jobId)
  {
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt,
        "SELECT DISTINCT child.jq_pk AS child_id,
                child.jq_type AS child_agent,
                child.jq_end_bits AS child_status
         FROM jobdepends jd
         JOIN jobqueue child ON child.jq_pk = jd.jdep_jq_fk
         WHERE jd.jdep_jq_depends_fk IN (
           SELECT jq_pk FROM jobqueue WHERE jq_job_fk = $1
         )
         ORDER BY child.jq_pk");
    $result = $this->dbManager->execute($stmt, array($jobId));
    $dependencies = [];
    while ($row = $this->dbManager->fetchArray($result)) {
      $dependencies[] = $row;
    }
    $this->dbManager->freeResult($result);
    return $dependencies;
  }
}
