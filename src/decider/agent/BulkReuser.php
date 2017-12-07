<?php
/*
 Copyright (C) 2015-2018, Siemens AG

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
  
  public function rerunBulkAndDeciderOnUpload($uploadId, $groupId, $userId, $bulkId, $dependency)
  {
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
    $res = $this->dbManager->execute($stmt,array($userId,$groupId,$uploadId,$topItem, $bulkId));
    $row = $this->dbManager->fetchArray($res);
    $this->dbManager->freeResult($res);
    $resLic = $this->dbManager->execute($stmtLic,array($row['lrb_pk'],$row['lrb_origin']));
    $this->dbManager->freeResult($resLic);
    $upload = $uploadDao->getUpload($uploadId);
    $uploadName = $upload->getFilename();
    $job_pk = \JobAddJob($userId, $groupId, $uploadName, $uploadId);
    /** @var DeciderJobAgentPlugin $deciderPlugin */
    $deciderPlugin = plugin_find("agent_deciderjob");
    $dependecies = array(array('name' => 'agent_monk_bulk', 'args' => $row['lrb_pk']));
    $errorMsg = '';
    $jqId = $deciderPlugin->AgentAdd($job_pk, $uploadId, $errorMsg, $dependecies);

    if (!empty($errorMsg)) {
      throw new Exception(str_replace('<br>', "\n", $errorMsg));
    }
    return $jqId;
  }
}
