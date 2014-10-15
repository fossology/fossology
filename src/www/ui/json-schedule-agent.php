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
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;

define("TITLE_scheduleAgentAjax", _("Private: schedule a agent scan from post"));

class scheduleAgentAjax extends FO_Plugin
{
  /** @var DbManager */
  private $dbManager;
  /** @var UploadDao */
  private $uploadDao;

  function __construct()
  {
    $this->Name = "scheduleAgentAjax";
    $this->Title = TITLE_scheduleAgentAjax;
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->OutputType = 'JSON';
    $this->LoginFlag = 0;

    parent::__construct();

    global $container;
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
    $groupId = $_SESSION['GroupId'];
    $uploadId = intval($_POST['uploadId']);
    $agentName = $_POST['agentName'];

    if ($uploadId > 0) {
      $uploadInfo = $this->uploadDao->getUploadInfo($uploadId);
      $uploadName = $uploadInfo['upload_filename'];
      $job_pk = JobAddJob($userId, $groupId, $uploadName, $uploadId);

      $ourPlugin = plugin_find($agentName);
      $jq_pk = $ourPlugin->AgentAdd($job_pk, $uploadId, $ErrorMsg, array());

    } else {
      $ErrorMsg = "bad request";
    }

    ReportCachePurgeAll();

    if (empty($ErrorMsg) && ($jq_pk>0)) {
      header('Content-type: text/json');
      return json_encode(array("jqid" => $jq_pk));
    } else {
      header('Content-type: text/json', true, 500);
      return json_encode(array("error" => $ErrorMsg));
    }
  }

}

$NewPlugin = new scheduleAgentAjax;
$NewPlugin->Initialize();


