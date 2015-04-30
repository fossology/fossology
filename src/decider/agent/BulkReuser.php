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

namespace Fossology\Decider;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;

class BulkReuser extends Object
{
  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    $this->dbManager = $GLOBALS['container']->get('db.manager');
  }

  private function getRecursiveReuseCte($uploadId, $groupId, $userId)
  {
    return "WITH RECURSIVE reuse_tree(reused_upload_fk, reused_group_fk, pairs, cycle) AS (
          SELECT reused_upload_fk, reused_group_fk,
            array[ARRAY[reused_upload_fk, reused_group_fk]], false
          FROM upload_reuse ur
          WHERE upload_fk=$uploadId AND group_fk=$groupId
        UNION ALL
          SELECT ur.reused_upload_fk, ur.reused_group_fk,
            pairs || array[ARRAY[ur.reused_upload_fk, ur.reused_group_fk]],
            array[ARRAY[ur.reused_upload_fk, ur.reused_group_fk]] <@ pairs
          FROM upload_reuse ur, reuse_tree rt
          WHERE NOT cycle AND ur.upload_fk=rt.reused_upload_fk
           AND ur.group_fk=rt.reused_group_fk
           AND EXISTS(SELECT * FROM group_user_member gum WHERE gum.group_fk=ur.group_fk AND gum.user_fk=$userId)
        )";
  }
  
  protected function getBulkIds($uploadId, $groupId, $userId)
  {
    $sql = $this->getRecursiveReuseCte('$1', '$2', '$3')
         ."  SELECT jq_args FROM reuse_tree, jobqueue, job 
             WHERE NOT cycle 
             AND jq_type=$4 AND jq_job_fk=job_pk
             AND job_upload_fk=reused_upload_fk AND job_group_fk=reused_group_fk";
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt,array($uploadId, $groupId, $userId,'monkbulk'));
    $bulkIds = array();
    while($row=  $this->dbManager->fetchArray($res))
    {
      $bulkIds = array_merge($bulkIds,explode("\n", $row['jq_args']));      
    }
    $this->dbManager->freeResult($res);
    return array_unique($bulkIds);
  }
  
  
  public function rerunBulkAndDeciderOnUpload($uploadId, $groupId, $userId) {
    $bulkIds = $this->getBulkIds($uploadId, $groupId, $userId);
    if (count($bulkIds) == 0) {
      return 0;
    }
    $upload = $GLOBALS['container']->get('dao.upload')->getUpload($uploadId);
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