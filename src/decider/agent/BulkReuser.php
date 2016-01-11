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

use Fossology\DeciderJob\UI\DeciderJobAgentPlugin;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\AgentPlugin;
use Fossology\Lib\Util\Object;
use Symfony\Component\Config\Definition\Exception\Exception;

class BulkReuser extends Object
{
  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    $this->dbManager = $GLOBALS['container']->get('db.manager');
  }

  protected function getBulkIds($uploadId, $groupId, $userId)
  {
    $sql = "SELECT jq_args FROM upload_reuse, jobqueue, job 
           WHERE upload_fk=$1 AND group_fk=$2
             AND EXISTS(SELECT * FROM group_user_member gum WHERE gum.group_fk=upload_reuse.group_fk AND gum.user_fk=$3)
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
  
  public function rerunBulkAndDeciderOnUpload($uploadId, $groupId, $userId, $jobId) {
    $bulkIds = $this->getBulkIds($uploadId, $groupId, $userId);
    if (count($bulkIds) == 0) {
      return 0;
    }
    /* @var $uploadDao UploadDao */
    $uploadDao = $GLOBALS['container']->get('dao.upload');
    $topItem = $uploadDao->getUploadParent($uploadId);
    /* @var $deciderPlugin DeciderJobAgentPlugin */
    $deciderPlugin = plugin_find("agent_deciderjob");
    $dependecies = array();
    $sql = "INSERT INTO license_ref_bulk (user_fk,group_fk,rf_text,upload_fk,uploadtree_fk) "
            . "SELECT $1 AS user_fk, $2 AS group_fk,rf_text,$3 AS upload_fk, $4 as uploadtree_fk
              FROM license_ref_bulk WHERE lrb_pk=$5 RETURNING lrb_pk, $5 as lrb_origin";
    $sqlLic = "INSERT INTO license_set_bulk (lrb_fk,rf_fk,removing) "
            ."SELECT $1 as lrb_fk,rf_fk,removing FROM license_set_bulk WHERE lrb_fk=$2";
    $this->dbManager->prepare($stmt=__METHOD__.'cloneBulk', $sql);
    $this->dbManager->prepare($stmtLic=__METHOD__.'cloneBulkLic', $sqlLic);
    foreach($bulkIds as $bulkId) {
      $res = $this->dbManager->execute($stmt,array($userId,$groupId,$uploadId,$topItem, $bulkId));
      $row = $this->dbManager->fetchArray($res);
      $this->dbManager->freeResult($res);
      $resLic = $this->dbManager->execute($stmtLic,array($row['lrb_pk'],$row['lrb_origin']));
      $this->dbManager->freeResult($resLic);  
      $dependecies[] = array('name' => 'agent_monk_bulk', 'args' => $row['lrb_pk'], AgentPlugin::PRE_JOB_QUEUE=>array('agent_decider'));
    }
    $errorMsg = '';
    $jqId = $deciderPlugin->AgentAdd($jobId, $uploadId, $errorMsg, $dependecies);
    if (!empty($errorMsg))
    {
      throw new Exception($errorMsg);
    }
    return $jqId;
  }

}
