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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Symfony\Component\HttpFoundation\JsonResponse;

define("TITLE_changeLicProcPost", _("Private: Change license file post"));

class changeLicenseProcessPost extends FO_Plugin
{
  /** @var ClearingDao */
  private $clearingDao;
  /** @var UploadDao */
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
      exec($command);
    }
  }

  /**
   * @brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return;
    }
    $itemId = $_POST['uploadTreeId'];
    if (empty($itemId))
    {
      return $this->errorJson("bad item id");
    }

    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();
    $decisionMark = @$_POST['decisionMark'];
    if(!empty($decisionMark))
    {
      $itemTableName = $this->uploadDao->getUploadtreeTableName($itemId);
      /** @var ItemTreeBounds */
      $itemTreeBounds = $this->uploadDao->getItemTreeBounds($itemId,$itemTableName);
      $errMsg = $this->clearingDao->markDirectoryAsIrrelevant($itemTreeBounds,$groupId,$userId);
      if (empty($errMsg))
      {
        return new JsonResponse(array('result'=>'success'));
      }
      return $this->errorJson($errMsg,$errMsg);
    }

    return $this->doEdit($userId,$groupId,$itemId);
  }

  function doEdit($userId,$groupId,$itemId)
  {
    $licenses = GetParm("licenseNumbersToBeSubmitted", PARM_RAW);
    $removed = $_POST['removed'] === 't' || $_POST['removed'] === 'true';

    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($itemId);
    $uploadId = $itemTreeBounds->getUploadId();
    $upload = $this->uploadDao->getUpload($uploadId);
    $uploadName = $upload->getFilename();

    $jobId = JobAddJob($userId, $groupId, $uploadName, $uploadId);

    if (isset($licenses))
    {
      if (!is_array($licenses))
      {
        return $this->errorJson("bad license array");
      }
      foreach ($licenses as $licenseId)
      {
        if (intval($licenseId) <= 0)
        {
          return $this->errorJson("bad license");
        }

        $this->clearingDao->insertClearingEvent($itemId, $userId, $groupId, $licenseId, $removed,
            ClearingEventTypes::USER, $reportInfo = '', $comment = '', $jobId);
      }
    }

    /** @var agent_fodecider $deciderPlugin */
    $deciderPlugin = plugin_find("agent_deciderjob");

    $conflictStrategyId = null;
    $errorMsg = "";
    $jq_pk = $deciderPlugin->AgentAdd($jobId, $uploadId, $errorMsg, array(), $conflictStrategyId);

    /** after changing one license, purge all the report cache */
    ReportCachePurgeAll();

    //Todo: Change sql statement of fossology/src/buckets/agent/leaf.c line 124 to take the newest valid license, then uncomment this line
    // $this->ChangeBuckets(); // change bucket accordingly

    if (empty($errorMsg) && ($jq_pk>0)) {
      return new JsonResponse(array("jqid" => $jq_pk));
    }
    else {
      return $this->errorJson($errorMsg, 500);
    }
  }

  private function errorJson($msg, $code = 404)
  {
    return new JsonResponse(array("error" => $msg), $code);
  }

}

$NewPlugin = new changeLicenseProcessPost;
$NewPlugin->Initialize();


