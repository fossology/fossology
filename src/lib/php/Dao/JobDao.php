<?php
/***********************************************************
 * Copyright (C) 2015 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;
use Monolog\Logger;

class JobDao extends Object
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

  public function getAllJobStatus($uploadId, $userId, $groupId) {
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
    while ($row = $this->dbManager->fetchArray($res))
    {
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
    while ($row = $this->dbManager->fetchArray($res))
    {
      $result[$row['jq_pk']] = $row['end_bits'];
    }
    $this->dbManager->freeResult($res);

    return $result;
  }
}