<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
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
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;

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
      header('Content-type: text/json', true, 500);
      return json_encode(array("error" => 'bad request'));
    }

    try
    {
      $jobQueueId = $this->getJobQueueId($uploadTreeId);
    } catch (Exception $ex)
    {
      $errorMsg = $ex->getMessage();
      header('Content-type: text/json', true, 500);
      return json_encode(array("error" => $errorMsg));
    }
    ReportCachePurgeAll();

    header('Content-type: text/json');
    return json_encode(array("jqid" => $jobQueueId));
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
    if ($bulkScope === 'u')
    {
      $uploadTreeTable = $this->uploadDao->getUploadtreeTableName($uploadId);
      $row = $this->dbManager->getSingleRow("SELECT uploadtree_pk FROM $uploadTreeTable WHERE upload_fk = $1 ORDER BY uploadtree_pk LIMIT 1",
          array($uploadId), __METHOD__ . "adam" . $uploadTreeTable);
      $uploadTreeId = $row['uploadtree_pk'];
    } else if (!Isdir($uploadEntry['ufile_mode']) && !Iscontainer($uploadEntry['ufile_mode']) && !Isartifact($uploadEntry['ufile_mode']))
    {
      $uploadTreeId = $uploadEntry['parent'] ?: $uploadTreeId;
    } else
    {
      throw new Exception('bad scope request');
    }

    $userId = $_SESSION['UserId'];
    $groupId = $_SESSION['GroupId'];
    $refText = filter_input(INPUT_POST, 'refText');
    $action = filter_input(INPUT_POST, 'bulkAction');
    if ($action === 'new')
    {
      $shortnamePattern = '/^[-+_a-z0-9\\.()]{3,100}$/i';
      $newShortname = filter_input(INPUT_POST, 'shortname');
      if (!preg_match($shortnamePattern, $newShortname))
      {
        throw new Exception('invalid shortname pattern');
      }
      if (!$this->licenseDao->isNewLicense($newShortname))
      {
        throw new Exception('license shortname already in use');
      }
      $licenseId = $this->licenseDao->insertUploadLicense($newShortname, $refText);
      $bulkId = $this->licenseDao->insertBulkLicense($userId, $groupId, $uploadTreeId, $licenseId, false, $refText);
    } else
    {
      $licenseId = GetParm('licenseId', PARM_INTEGER);
      $removing = ($action === 'remove');
      $bulkId = $this->licenseDao->insertBulkLicense($userId, $groupId, $uploadTreeId, $licenseId, $removing, $refText);
    }

    if ($bulkId <= 0)
    {
      throw new Exception('cannot insert bulk reference');
    }
    $uploadInfo = $this->uploadDao->getUploadInfo($uploadId);
    $uploadName = $uploadInfo['upload_filename'];
    $job_pk = JobAddJob($userId, $groupId, $uploadName, $uploadId);
    /** @var agent_fodecider $deciderPlugin */
    $deciderPlugin = plugin_find("agent_decider");
    $dependecies = array(array('name' => 'agent_monk_bulk', 'args' => $bulkId));
    $conflictStrategyId = intval(filter_input(INPUT_POST, 'forceDecision'));
    $errorMsg = '';
    $jq_pk = $deciderPlugin->AgentAdd($job_pk, $uploadId, $errorMsg, $dependecies, $conflictStrategyId);

    if (!empty($errorMsg))
    {
      throw new Exception(str_replace('<br>', "\n", $errorMsg));
    }
    return $jq_pk;
  }

}

$NewPlugin = new changeLicenseBulk;
$NewPlugin->Initialize();
