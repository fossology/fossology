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
      "SELECT jobqueue.jq_pk as jq_pk, jobqueue.jq_end_bits as end_bits
      FROM jobqueue INNER JOIN job ON jobqueue.jq_job_fk = job.job_pk
      LEFT JOIN group_user_member gm ON gm.user_fk = job_user_fk
      WHERE job.job_upload_fk = $1 AND (job_user_fk = $2 OR gm.group_fk = $3)");

    $res = $this->dbManager->execute($stmt, array($uploadId, $userId, $groupId));
    while ($row = $this->dbManager->fetchArray($res))
    {
      $result[$row['jq_pk']] = $row['end_bits'];
    }
    $this->dbManager->freeResult($res);

    return $result;
  }
  
  public function getJobQueue($jobType,$jobId)
  {
    $row = $this->dbManager->getSingleRow("SELECT jq_pk FROM jobqueue WHERE jq_type=$1 AND jq_job_fk=$2",
            array($jobType,$jobId));
    return $row['jq_pk'];
  }
  
  /**
   * @todo cycle detection
   * @param int $jobqueueId
   * @param int $jobId
   * @param array $preAgents
   * @return boolean 
   */
  public function requeueJob($jobqueueId, $jobId, $preAgents)
  {
    $arrayAgents = '{'.implode(',',$preAgents).'}';
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $sql="SELECT jobqueue.jq_pk FROM jobqueue
 LEFT JOIN jobdepends ON jobdepends.jdep_jq_fk=$1 AND jobdepends.jdep_jq_depends_fk=jobqueue.jq_pk AND jq_end_bits!=$4
 WHERE jobqueue.jq_job_fk=$2 AND jobqueue.jq_type=ANY($3) AND jobdepends.jdep_jq_fk IS NULL");
    $res = $this->dbManager->execute($stmt,array($jobqueueId,$jobId,$arrayAgents,1));
    $results = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    if (empty($results))
    {
      return false;
    }
    foreach($results as $jq)
    {
      if ($jq['jq_pk'] == $jobqueueId)
      {
        throw new \Exception('loop detected');
    }
      $this->dbManager->insertTableRow('jobdepends', array('jdep_jq_fk'=>$jobqueueId,'jdep_jq_depends_fk'=>$jq['jq_pk']));
    }
    return true;
  }
}