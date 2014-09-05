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

define("TITLE_scheduleAgentAjax", _("Private: schedule a agent scan from post"));

class scheduleAgentAjax extends FO_Plugin
{

  /**
   * @var DbManager
   */
  private $dbManager;

  /**
   * @var UploadDao
   */
  private $uploadDao;

  function __construct()
  {
    $this->Name = "scheduleAgentAjax";
    $this->Title = TITLE_scheduleAgentAjax;
    $this->Version = "1.0";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->NoHTML = 1;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;

    parent::__construct();

    global $container;
    $this->licenseDao = $container->get('dao.license');
    $this->uploadDao = $container->get('dao.upload');
    $this->dbManager = $container->get('db.manager');
  }

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $ErrorMsg = "";
    $jq_pk = -1;

    $userId = $_SESSION['UserId'];
    $uploadId = intval($_POST['uploadId']);
    $agentName = $_POST['agentName'];


    if ($uploadId > 0) {
      $uploadInfo = $this->uploadDao->getUploadInfo($uploadId);
      $uploadName = $uploadInfo['upload_filename'];
      $job_pk = JobAddJob($userId, $uploadName, $uploadId);

      global $Plugins;
      $ourPlugin = plugin_find($agentName);
      $jq_pk = $ourPlugin->AgentAdd($job_pk, $uploadId, $ErrorMsg, array());

    } else {
      $ErrorMsg = "bad request";
    }

    ReportCachePurgeAll();

    if (empty($ErrorMsg) && ($jq_pk>0)) {
      header('Content-type: text/json');
      print json_encode(array("jqid" => $jq_pk));
    } else {
      header('Content-type: text/json', true, 500);
      print json_encode(array("error" => $ErrorMsg));
    }
  } // Output()

}

$NewPlugin = new scheduleAgentAjax;
$NewPlugin->Initialize();


