<?php
/***********************************************************
 * Copyright (C) 2014-2015 Siemens AG
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

use Fossology\DeciderJob\UI\DeciderJobAgentPlugin;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Symfony\Component\HttpFoundation\Response;

define("TITLE_changeLicenseBulk", _("Private: schedule a bulk scan from post"));

class changeLicenseBulk extends FO_Plugin
{
  /** @var LicenseDao */
  private $licenseDao;
  /** @var DbManager */
  private $dbManager;
  /** @var UploadDao */
  private $uploadDao;

  function __construct()
  {
    $this->Name = "change-license-bulk";
    $this->Title = TITLE_changeLicenseBulk;
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->OutputType = 'JSON';
    $this->LoginFlag = 0;
    $this->NoMenu = 0;

    parent::__construct();

    global $container;
    $this->licenseDao = $container->get('dao.license');
    $this->uploadDao = $container->get('dao.upload');
    $this->dbManager = $container->get('db.manager');
  }

  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      throw new \Fossology\Lib\Exception("plugin " . $this->Name . " is not ready");
    }

    $uploadTreeId = intval($_POST['uploadTreeId']);
    if ($uploadTreeId <= 0)
    {
      return new Response(json_encode(array("error" => 'bad request')), 500, array('Content-type'=>'text/json'));
    }

    try
    {
      $jobQueueId = $this->getJobQueueId($uploadTreeId);
    } catch (Exception $ex)
    {
      $errorMsg = $ex->getMessage();
      return new Response(json_encode(array("error" => $errorMsg)), 500, array('Content-type'=>'text/json'));
    }
    ReportCachePurgeAll();
    
    return new Response(json_encode(array("jqid" => $jobQueueId)), Response::HTTP_OK, array('Content-type'=>'text/json'));
  }

  private function getJobQueueId($uploadTreeId)
  {
    $uploadEntry = $this->uploadDao->getUploadEntry($uploadTreeId);
    $uploadId = intval($uploadEntry['upload_fk']);
    if ($uploadId <= 0)
    {
      throw new Exception('permission denied');
    }

    $bulkScope = filter_input(INPUT_POST, 'bulkScope');
    switch ($bulkScope)
    {
      case 'u':
        $uploadTreeTable = $this->uploadDao->getUploadtreeTableName($uploadId);
        $row = $this->dbManager->getSingleRow("SELECT uploadtree_pk FROM $uploadTreeTable WHERE upload_fk = $1 ORDER BY uploadtree_pk LIMIT 1",
            array($uploadId), __METHOD__ . "adam" . $uploadTreeTable);
        $uploadTreeId = $row['uploadtree_pk'];
        break;

      case 'f':
        if (!Isdir($uploadEntry['ufile_mode']) && !Iscontainer($uploadEntry['ufile_mode']) && !Isartifact($uploadEntry['ufile_mode']))
        {
          $uploadTreeId = $uploadEntry['parent'] ?: $uploadTreeId;
        }
        break;

      default:
        throw new InvalidArgumentException('bad scope request');
    }

    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();
    $refText = filter_input(INPUT_POST, 'refText');
    $action = filter_input(INPUT_POST, 'bulkAction');
    $licenseId = GetParm('licenseId', PARM_INTEGER);
    $removing = ($action === 'remove');
    $bulkId = $this->licenseDao->insertBulkLicense($userId, $groupId, $uploadTreeId, $licenseId, $removing, $refText);

    if ($bulkId <= 0)
    {
      throw new Exception('cannot insert bulk reference');
    }
    $upload = $this->uploadDao->getUpload($uploadId);
    $uploadName = $upload->getFilename();
    $job_pk = JobAddJob($userId, $groupId, $uploadName, $uploadId);
    /** @var DeciderJobAgentPlugin $deciderPlugin */
    $deciderPlugin = plugin_find("agent_deciderjob");
    $dependecies = array(array('name' => 'agent_monk_bulk', 'args' => $bulkId));
    $conflictStrategyId = intval(filter_input(INPUT_POST, 'forceDecision'));
    $errorMsg = '';
    $jqId = $deciderPlugin->AgentAdd($job_pk, $uploadId, $errorMsg, $dependecies, $conflictStrategyId);

    if (!empty($errorMsg))
    {
      throw new Exception(str_replace('<br>', "\n", $errorMsg));
    }
    return $jqId;
  }

}

$NewPlugin = new changeLicenseBulk;
$NewPlugin->Initialize();
