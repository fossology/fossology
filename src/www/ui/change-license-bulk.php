<?php
/*
 SPDX-FileCopyrightText: © 2014-2015, 2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\DeciderJob\UI\DeciderJobAgentPlugin;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ChangeLicenseBulk extends DefaultPlugin
{
  const NAME = "change-license-bulk";
  /** @var LicenseDao */
  private $licenseDao;
  /** @var DbManager */
  private $dbManager;
  /** @var UploadDao */
  private $uploadDao;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Private: schedule a bulk scan from post"),
        self::PERMISSION => Auth::PERM_WRITE
    ));

    $this->dbManager = $this->getObject('db.manager');
    $this->licenseDao = $this->getObject('dao.license');
    $this->uploadDao = $this->getObject('dao.upload');
  }

  /**
   * @param Request $request
   * @return Response
   */
  public function handle(Request $request)
  {
    $uploadTreeId = $request->get('uploadTreeId');
    $uploadTreeId = strpos($uploadTreeId, ',') !== false
      ? explode(',', $uploadTreeId)
      : intval($uploadTreeId);

    if (empty($uploadTreeId)) {
      return new JsonResponse(array("error" => 'bad request'), JsonResponse::HTTP_BAD_REQUEST);
    }

    try {
      $jobQueueId = $this->scheduleBulkScan($uploadTreeId, $request);
    } catch (Exception $ex) {
      $errorMsg = $ex->getMessage();
      return new JsonResponse(array("error" => $errorMsg), JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
    ReportCachePurgeAll();

    return new JsonResponse(array("jqid" => $jobQueueId));
  }

  /**
   *
   * @param int $uploadTreeId
   * @param Request $request
   * @return int $jobQueueId
   */
  private function scheduleBulkScan($uploadTreeId, Request $request)
  {
    if (is_array($uploadTreeId)) {
      $jqId = array();
      foreach ($uploadTreeId as $uploadTreePk) {
        $jqId[] = $this->getJobQueueId($uploadTreePk, $request);
      }
      return $jqId;
    } else {
      return $this->getJobQueueId($uploadTreeId, $request);
    }
  }
  /**
   *
   * @param int $uploadTreeId
   * @param Request $request
   * @return int $jobQueueId
   */
  private function getJobQueueId($uploadTreeId, Request $request)
  {
    $uploadEntry = $this->uploadDao->getUploadEntry($uploadTreeId);
    $uploadId = intval($uploadEntry['upload_fk']);
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();

    if ($uploadId <= 0 || !$this->uploadDao->isAccessible($uploadId, $groupId)) {
      throw new Exception('permission denied');
    }

    $bulkScope = $request->get('bulkScope');
    switch ($bulkScope) {
      case 'u':
        $uploadTreeTable = $this->uploadDao->getUploadtreeTableName($uploadId);
        $topBounds = $this->uploadDao->getParentItemBounds($uploadId, $uploadTreeTable);
        $uploadTreeId = $topBounds->getItemId();
        break;

      case 'f':
        if (!Isdir($uploadEntry['ufile_mode']) &&
            !Iscontainer($uploadEntry['ufile_mode']) &&
            !Isartifact($uploadEntry['ufile_mode'])) {
          $uploadTreeId = $uploadEntry['parent'] ?: $uploadTreeId;
        }
        break;

      default:
        throw new InvalidArgumentException('bad scope request');
    }

    $refText = $request->get('refText');
    $actions = $request->get('bulkAction');
    $ignoreIrrelevantFiles = (intval($request->get('ignoreIrre')) == 1);
    $scanFindingsOnly = boolval(intval($request->get('scanOnlyFindings')));
    $delimiters = $request->get('delimiters');

    $licenseRemovals = array();
    foreach ($actions as $licenseAction) {
      $licenseRemovals[$licenseAction['licenseId']] = array(($licenseAction['action']=='Remove'), $licenseAction['comment'], $licenseAction['reportinfo'], $licenseAction['acknowledgement']);
    }
    $bulkId = $this->licenseDao->insertBulkLicense($userId, $groupId,
      $uploadTreeId, $licenseRemovals, $refText, $ignoreIrrelevantFiles,
      $delimiters, $scanFindingsOnly);

    if ($bulkId <= 0) {
      throw new Exception('cannot insert bulk reference');
    }

    // Handle adding text phrase to custom_phrase table if checkbox is checked
    $addToCustomPhrase = intval($request->get('addToCustomPhrase'));
    if ($addToCustomPhrase == 1) {
      $this->importBulkDataToCustomPhrase($bulkId, $userId, $groupId);
    }
    $upload = $this->uploadDao->getUpload($uploadId);
    $uploadName = $upload->getFilename();
    $job_pk = JobAddJob($userId, $groupId, $uploadName, $uploadId);
    /** @var DeciderJobAgentPlugin $deciderPlugin */
    $deciderPlugin = plugin_find("agent_deciderjob");

    $dependecies = array(array('name' => 'agent_monk_bulk', 'args' => $bulkId));

    $conflictStrategyId = intval($request->get('forceDecision'));
    $errorMsg = '';
    $jqId = $deciderPlugin->AgentAdd($job_pk, $uploadId, $errorMsg, $dependecies, $conflictStrategyId);

    if (!empty($errorMsg)) {
      throw new Exception(str_replace('<br>', "\n", $errorMsg));
    }
    return $jqId;
  }

  /**
   * Import bulk data from license_ref_bulk to custom_phrase table
   * This reuses the data that was just inserted into license_ref_bulk
   *
   * @param int $bulkId The lrb_pk from license_ref_bulk table
   * @param int $userId User ID
   * @param int $groupId Group ID
   * @return void
   */
  private function importBulkDataToCustomPhrase($bulkId, $userId, $groupId)
  {
    // Fetch the bulk reference text from license_ref_bulk
    $bulkDataSql = "SELECT rf_text FROM license_ref_bulk WHERE lrb_pk = $1";
    $this->dbManager->prepare($bulkStmt = __METHOD__ . ".getBulkData", $bulkDataSql);
    $bulkResult = $this->dbManager->execute($bulkStmt, array($bulkId));
    $bulkRow = $this->dbManager->fetchArray($bulkResult);
    $this->dbManager->freeResult($bulkResult);

    if ($bulkRow === false) {
      error_log("Failed to fetch bulk data for lrb_pk: $bulkId");
      return;
    }

    $refText = $bulkRow['rf_text'];
    $textMd5 = md5($refText);

    // Check if duplicate exists
    $checkSql = "SELECT cp_pk FROM custom_phrase WHERE text_md5 = $1";
    $this->dbManager->prepare($checkStmt = __METHOD__ . ".checkDuplicate", $checkSql);
    $checkResult = $this->dbManager->execute($checkStmt, array($textMd5));
    $existingPhrase = $this->dbManager->fetchArray($checkResult);
    $this->dbManager->freeResult($checkResult);

    if ($existingPhrase !== false) {
      // Duplicate exists, skip insertion
      error_log("Custom phrase with MD5 hash $textMd5 already exists. Skipping insertion.");
      return;
    }

    // Fetch associated licenses from license_set_bulk (both adding and removing licenses)
    $licensesSql = "SELECT rf_fk, COALESCE(removing, false) as removing FROM license_set_bulk
                    WHERE lrb_fk = $1";
    $this->dbManager->prepare($licenseStmt = __METHOD__ . ".getLicenses", $licensesSql);
    $licensesResult = $this->dbManager->execute($licenseStmt, array($bulkId));

    $licenses = array();
    while ($licenseRow = $this->dbManager->fetchArray($licensesResult)) {
      $licenses[] = array(
        'rf_fk' => intval($licenseRow['rf_fk']),
        'removing' => $licenseRow['removing'] === 't' || $licenseRow['removing'] === true
      );
    }
    $this->dbManager->freeResult($licensesResult);

    // Start transaction to insert into custom_phrase
    $this->dbManager->begin();
    try {
      // Insert into custom_phrase table
      $insertSql = "INSERT INTO custom_phrase
                    (text, text_md5, acknowledgement, comments, user_fk, group_fk, is_active, created_date)
                    VALUES ($1, $2, $3, $4, $5, $6, $7, CURRENT_TIMESTAMP) RETURNING cp_pk";
      $params = array($refText, $textMd5, '', '', $userId, $groupId, 'true');
      $this->dbManager->prepare($insertStmt = __METHOD__ . ".insertPhrase", $insertSql);
      $result = $this->dbManager->execute($insertStmt, $params);
      $row = $this->dbManager->fetchArray($result);

      if ($row === false) {
        $this->dbManager->freeResult($result);
        throw new Exception('Failed to insert custom phrase');
      }

      $cpPk = $row['cp_pk'];
      $this->dbManager->freeResult($result);

      // Insert license associations into custom_phrase_license_map
      if (!empty($licenses)) {
        $mapSql = "INSERT INTO custom_phrase_license_map (cp_fk, rf_fk, removing) VALUES ($1, $2, $3)";
        $this->dbManager->prepare($mapStmt = __METHOD__ . ".insertLicenseMap", $mapSql);

        foreach ($licenses as $license) {
          $mapResult = $this->dbManager->execute($mapStmt, array($cpPk, $license['rf_fk'], $license['removing'] ? 'true' : 'false'));
          $this->dbManager->freeResult($mapResult);
        }
      }

      $this->dbManager->commit();
      error_log("Custom phrase imported successfully from bulk data. cp_pk: $cpPk, lrb_pk: $bulkId");
    } catch (Exception $e) {
      $this->dbManager->rollback();
      error_log("Error importing bulk data to custom phrase: " . $e->getMessage());
      // Don't throw exception to avoid breaking the bulk scan
      // Just log the error and continue
    }
  }
}

register_plugin(new ChangeLicenseBulk());
