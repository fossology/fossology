<?php
/*
 Copyright (C) 2015, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

namespace Fossology\Reuser;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;

class BulkReuserAgent extends Object
{
  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    $this->dbManager = $GLOBALS['container']->get('db.manager');
  }

  function getRelevantReuse($uploadId, $groupId, $userId)
  {
    $sql = "WITH RECURSIVE reuse_tree(upload_fk, reused_upload_fk, group_fk, reused_group_fk, pairs, cycle) AS (
          SELECT upload_fk, reused_upload_fk, group_fk, reused_group_fk,
            array[ARRAY[upload_fk, reused_upload_fk]], false
          FROM upload_reuse ur
          WHERE upload_fk=$1 AND group_fk=$2
        UNION ALL
          SELECT upload_fk, reused_upload_fk, group_fk, reused_group_fk,
            pairs || array[ARRAY[upload_fk, reused_upload_fk]], array[ARRAY[upload_fk, reused_upload_fk]] <@ pairs
          FROM upload_reuse ur, reuses rt
          WHERE NOT cycle AND ur.upload_fk=rt.reused_upload_fk
           AND ur.group_fk=rt.reused_group_fk
           AND EXISTS(SELECT * FROM group_user_member gum WHERE gum.group_fk=ur.group_fk AND gum.user_fk=$3)
        )
        SELECT reused_upload_fk, reused_group_fk FROM reuse_tree WHERE NOT cycle GROUP BY group_fk, reused_group_fk";
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt,array($uploadId, $groupId, $userId));
    $rows = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    return $rows;            
  }  

  function getBulkIds($relevantReuse){
    $bulkIds = array();
    $this->dbManager->prepare($stmt=__METHOD__,
        "SELECT jq_args FROM jobqueue, job"
            . "WHERE jq_type='monkbulk' AND jq_job_fk=job_pk AND job_upload_fk=$1 AND job_group_fk=$2");
    foreach ($relevantReuse as $reuse) {
      $res = $this->dbManager->execute($stmt, array($reuse['reused_upload_fk'], $reuse['reused_group_fk']));
      $arg = implode("\n",array_values($this->dbManager->fetchAll($res)));
      $this->dbManager->freeResult($res);
      $bulkIds = array_merge($bulkIds,explode("\n", $arg));
    }
    return $bulkIds;
  }
  
  
  public function rerunBulkAndDeciderOnUpload($uploadId, $groupId, $userId) {
    $relevantReuse = $this->getRelevantReuse($uploadId, $groupId, $userId);
    if (count($relevantReuse) == 0) {
      return 0;
    }
    $bulkIds = $this->getBulkIds();
    if (count($bulkIds) == 0) {
      return 0;
    }
    $upload = $GLOBALS['dao.upload']->getUpload($uploadId);
    $uploadName = $upload->getFilename();
    $jobId = JobAddJob($userId, $groupId, $uploadName, $uploadId);
    /** @var DeciderJobAgentPlugin $deciderPlugin */
    $deciderPlugin = plugin_find("agent_deciderjob");
    $dependecies = array(array('name' => 'agent_monk_bulk', 'args' => implode("\n", $bulkIds)));
    $errorMsg = '';
    $jqId = $deciderPlugin->AgentAdd($jobId, $uploadId, $errorMsg, $dependecies);
    if (!empty($errorMsg))
    {
      throw new Exception($errorMsg);
    }
    return $jqId;
  }

}