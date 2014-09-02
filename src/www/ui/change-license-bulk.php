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

define("TITLE_changeLicenseBulk", _("Private: schedule a bulk scan from post"));

class changeLicenseBulk extends FO_Plugin
{
  /**
   * @var LicenseDao
   */
  private $licenseDao;

  /**
   * @var UploadDao
   */
  private $uploadDao;

  function __construct()
  {
    $this->Name = "change-license-bulk";
    $this->Title = TITLE_changeLicenseBulk;
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
  }

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }

    global $SysConf;
    $userId = $SysConf['auth']['UserId'];
    $groupId = 2; // TODO
    $uploadTreeId = $_POST['uploadTreeId'];
    $refText = $_POST['refText'];
    $licenseId = $_POST['licenseId'];
    $mode = $_POST['mode'];
    // TODO sanitize input

    $license = $this->licenseDao->getLicenseById($licenseId);
    $uploadEntry = $this->uploadDao->getUploadEntry($uploadTreeId);
    $uploadId = $uploadEntry['upload_fk'];
    $uploadInfo = $this->uploadDao->getUploadInfo($uploadId);
    $uploadName = $uploadInfo['upload_filename'];

    global $Plugins;
    $MonkBulkPlugin = plugin_find("agent_monk_bulk");

    $BulkSep = "\31";
    $jq_cmd_args = implode($BulkSep, array($mode, $userId, $groupId, $uploadTreeId, $licenseId, $refText));

    $job_pk = JobAddJob($userId, $uploadName, $uploadId);
    $ErrorMsg = "";
    $MonkBulkPlugin->AgentAdd($job_pk, $uploadId, &$ErrorMsg, array(), $jq_cmd_args);

    ReportCachePurgeAll();
    // TODO return ErrorMsg status
  } // Output()

}

$NewPlugin = new changeLicenseBulk;
$NewPlugin->Initialize();


