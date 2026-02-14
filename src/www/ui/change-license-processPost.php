<?php
/*
 SPDX-FileCopyrightText: © 2014-2018, 2020 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Symfony\Component\HttpFoundation\JsonResponse;

define("TITLE_CHANGELICPROCPOST", _("Private: Change license file post"));

class changeLicenseProcessPost extends FO_Plugin
{
  /** @var ClearingDao */
  private $clearingDao;
  /** @var UploadDao */
  private $uploadDao;
  /** @var Array $decisionSearch */
  private $decisionSearch = array(
      DecisionTypes::IRRELEVANT => "noLicenseKnown",
      DecisionTypes::NON_FUNCTIONAL => "nonFunctional",
      DecisionTypes::TO_BE_DISCUSSED => "toBeDiscussed",
      DecisionTypes::IRRELEVANT => "irrelevant",
      DecisionTypes::DO_NOT_USE => "doNotUse",
      DecisionTypes::IDENTIFIED => "identified"
    );

  function __construct()
  {
    $this->Name = "change-license-processPost";
    $this->Title = TITLE_CHANGELICPROCPOST;
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
    foreach ($bucketpool_array as $bucketpool) {
      $command = "$buckets_dir/buckets/agent/buckets -r -t ".escapeshellarg($uploadTreeId)." -p $bucketpool";
      exec($command);
    }
  }

  /**
   * @brief Display the loaded menu and plugins.
   */
  function Output()
  {
    $itemArray = array();
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $itemId = @$_POST['uploadTreeId'];
    if (empty($itemId)) {
      return $this->errorJson("Bad item id");
    }
    $itemArray = explode(',', $itemId);
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();
    $isRemoval = strtolower(@$_POST['isRemoval']);
    $isRemoval = $isRemoval == 't' || $isRemoval == 'true';
    $decisionMark = @$_POST['decisionMark'];
    $decisionMarkExists = array_search($decisionMark, $this->decisionSearch, true);

    if ( (!empty($decisionMark)) && $decisionMarkExists !== false ) {
      foreach ($itemArray as $uploadTreeId) {
        $responseMsg = $this->doMarkDecisionTypes($uploadTreeId, $groupId,
          $userId, $decisionMark, $isRemoval);
        if (! empty($responseMsg)) {
          return $responseMsg;
        }
      }
      return new JsonResponse(array('result'=>'success'));
    }
    return $this->doEdit($userId, $groupId, $itemArray);
  }

  function doMarkDecisionTypes($itemId, $groupId, $userId, $decisionMark, $isRemoval)
  {
    $itemTableName = $this->uploadDao->getUploadtreeTableName($itemId);
    /** @var ItemTreeBounds */
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($itemId, $itemTableName);
    if ($isRemoval) {
      $errMsg = $this->clearingDao->deleteDecisionTypeFromDirectory($itemTreeBounds, $groupId, $userId, $decisionMark);
    } else {
      $errMsg = $this->clearingDao->markDirectoryAsDecisionType($itemTreeBounds, $groupId, $userId, $decisionMark);
    }
    return $errMsg;
  }

  function doEdit($userId, $groupId, $itemArray)
  {
    $licenses = GetParm("licenseNumbersToBeSubmitted", PARM_RAW);
    $removed = strtolower(@$_POST['removed']);
    $removed = $removed == 't' || $removed == 'true';

    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($itemArray[0]);
    $uploadId = $itemTreeBounds->getUploadId();
    $upload = $this->uploadDao->getUpload($uploadId);
    $uploadName = $upload->getFilename();

    $jobId = JobAddJob($userId, $groupId, $uploadName, $uploadId);
    foreach ($itemArray as $itemId) {
      if (isset($licenses)) {
        if (! is_array($licenses)) {
          return $this->errorJson("bad license array");
        }
        
        // Check for existing events to prevent duplicates
        $itemBounds = $this->uploadDao->getItemTreeBounds($itemId);
        $existingEvents = $this->clearingDao->getRelevantClearingEvents($itemBounds, $groupId, false);
        
        foreach ($licenses as $licenseId) {
          if (intval($licenseId) <= 0) {
            return $this->errorJson("bad license");
          }

          // Only insert event if no identical event exists or status differs
          if (!isset($existingEvents[$licenseId]) || $existingEvents[$licenseId]->isRemoved() !== $removed) {
            $this->clearingDao->insertClearingEvent($itemId, $userId, $groupId,
              $licenseId, $removed, ClearingEventTypes::USER, $reportInfo = '',
              $comment = '', $acknowledgement = '', $jobId);
          }
        }
      }
    }

    /** @var agent_fodecider $deciderPlugin */
    $deciderPlugin = plugin_find("agent_deciderjob");

    $conflictStrategyId = null;
    $errorMsg = "";
    $jq_pk = $deciderPlugin->AgentAdd($jobId, $uploadId, $errorMsg, array(), $conflictStrategyId);

    /** after changing one license, purge all the report cache */
    ReportCachePurgeAll();

    /**
     * @todo Change sql statement of fossology/src/buckets/agent/leaf.c line 124 to
     * take the newest valid license, then uncomment this line
    // $this->ChangeBuckets(); // change bucket accordingly
     */

    if (empty($errorMsg) && ($jq_pk>0)) {
      return new JsonResponse(array("jqid" => $jq_pk));
    } else {
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
