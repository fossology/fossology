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
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Util\LicenseOverviewPrinter;

define("TITLE_changeLicProcPost", _("Private: Change license file post"));

class changeLicenseProcessPost extends FO_Plugin
{
  /** @var ClearingDao */
  private $clearingDao;
  /** @var LicenseOverviewPrinter */
  private $licenseOverviewPrinter;

  /**
   * @var UploadDao
   */
  private $uploadDao;

  function __construct()
  {
    $this->Name = "change-license-processPost";
    $this->Title = TITLE_changeLicProcPost;
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->OutputType = 'JSON';
    $this->OutputToStdout = 1;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;

    parent::__construct();

    global $container;
    $this->clearingDao = $container->get('dao.clearing');
    $this->licenseOverviewPrinter =  $container->get('utils.license_overview_printer');
    $this->uploadDao = $container->get('dao.upload');
  }

  /**
   * \brief change bucket accordingly when change license of one file
   */
  // TODO:  Understand Buckets and modify then
  function ChangeBuckets()
  {
    global $SysConf;
    global $PG_CONN;

    $uploadId = GetParm("upload", PARM_STRING);
    $uploadTreeId = GetParm("item", PARM_STRING);

    $sql = "SELECT bucketpool_fk from bucket_ars where upload_fk = $uploadId;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $bucketpool_array = pg_fetch_all_columns($result, 0);
    pg_free_result($result);
    $buckets_dir = $SysConf['DIRECTORIES']['MODDIR'];
    /** rerun bucket on the file */
    foreach ($bucketpool_array as $bucketpool)
    {
      $command = "$buckets_dir/buckets/agent/buckets -r -t $uploadTreeId -p $bucketpool";
      exec($command, $output, $return_var);
    }
  }

  /**
   * @brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }

    $userId = $_SESSION['UserId'];
    $groupId = $_SESSION['GroupId'];
    $itemId = $_POST['uploadTreeId'];
    $licenses = GetParm("licenseNumbersToBeSubmitted", PARM_RAW);
    $removed = $_POST['removed'];

    $uploadEntry = $this->uploadDao->getUploadEntry($itemId);
    $uploadId = intval($uploadEntry['upload_fk']);
    $uploadInfo = $this->uploadDao->getUploadInfo($uploadId);
    $uploadName = $uploadInfo['upload_filename'];


    $job_pk = JobAddJob($userId, $groupId, $uploadName, $uploadId);

    if(isset($licenses))
    $this->clearingDao->insertClearingDecisionTest($licenses, $removed, $itemId, $userId, $job_pk); //,  $_POST['comment'], $_POST['remark']


    /** @var agent_fodecider $deciderPlugin */
    $deciderPlugin = plugin_find("agent_decider");

    $conflictStrategyId = null; // TODO add option in GUI
    $ErrorMsg="";
    $jq_pk = $deciderPlugin->AgentAdd($job_pk, $uploadId, $ErrorMsg, array(), $conflictStrategyId);

    /** after changing one license, purge all the report cache */
    ReportCachePurgeAll();

    //Todo: Change sql statement of fossology/src/buckets/agent/leaf.c line 124 to take the newest valid license, then uncomment this line
    // $this->ChangeBuckets(); // change bucket accordingly


    if (empty($ErrorMsg) && ($jq_pk>0)) {
      header('Content-type: text/json');
      return json_encode(array("jqid" => $jq_pk));
    } else {
      header('Content-type: text/json', true, 500);
      return json_encode(array("error" => $ErrorMsg));
    }
  }


}

$NewPlugin = new changeLicenseProcessPost;
$NewPlugin->Initialize();


