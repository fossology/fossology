<?php
/*
 Author: Shaheem Azmal M MD <shaheem.azmal@siemens.com>
 SPDX-FileCopyrightText: © 2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

define("DECISIONEXPORTER_AGENT_NAME", "decisionexporter");

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\AllDecisionsDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;

include_once(__DIR__ . "/version.php");

/**
 * @class DecisionExporter
 * @brief Generates Decision Exporter JSON
 */
class DecisionExporter extends Agent
{
  /**
   * @var UploadDao $uploadDao
   */
  private $uploadDao;

  /** @var AllDecisionsDao $allDecisionsDao
   * AllDecisionsDao object
   */
  private $allDecisionsDao;

  /**
   * @var ClearingDao $clearingDao
   * ClearingDao object
   */
  private $clearingDao;

  function __construct()
  {
    parent::__construct(DECISIONEXPORTER_AGENT_NAME, AGENT_VERSION, AGENT_REV);

    $this->uploadDao = $this->container->get('dao.upload');
    $this->allDecisionsDao = $this->container->get('dao.alldecisions');
    $this->clearingDao = $this->container->get('dao.clearing');
  }


  /**
   * @copydoc Fossology::Lib::Agent::Agent::processUploadId()
   * @see Fossology::Lib::Agent::Agent::processUploadId()
   */
  function processUploadId($uploadId)
  {
    $groupId = $this->groupId;
    $userId = $this->userId;
    $tableName = "decision_exporter_pfile_" . $uploadId;

    $pfileData = $this->allDecisionsDao->getAllAgentPfileIdsForUpload($uploadId, $groupId, $userId);
    $this->createPfileTable($uploadId, $tableName);
    $this->heartbeat(count($pfileData));

    $this->insertPfileData($uploadId, $pfileData, $tableName);
    $this->heartbeat(1);
    $uploadTreeData = $this->allDecisionsDao->getAllAgentUploadTreeDataForUpload($uploadId, $tableName);
    $this->heartbeat(1);
    $clearingDecisonData = $this->allDecisionsDao->getAllClearingDecisionDataForUpload($uploadId, $tableName);
    $this->heartbeat(1);
    $clearingEventData = $this->allDecisionsDao->getAllClearingEventDataForUpload($uploadId, $tableName);
    $this->heartbeat(1);
    $clearingDecisonEventData = $this->allDecisionsDao->getAllClearingDecisionEventDataForUpload($uploadId, $tableName);
    $this->heartbeat(1);
    $licenseRefBulkData = $this->allDecisionsDao->getAllLicenseRefBulkDataForUpload($uploadId);
    $this->heartbeat(1);
    $licenseSetBulkData = $this->allDecisionsDao->getAllLicenseSetBulkDataForUpload($uploadId);
    $this->heartbeat(1);
    $bulkHighlightData = $this->allDecisionsDao->getAllBulkHighlightDataForUpload($uploadId);
    $this->heartbeat(1);
    $copyrightData = $this->allDecisionsDao->getAllDataForGivenTableUpload($tableName, 'copyright');
    $this->heartbeat(1);
    $copyrightDecisionData = $this->allDecisionsDao->getAllDataForGivenDecisionTableUpload($tableName, 'copyright_decision');
    $this->heartbeat(1);
    $copyrightEventData = $this->allDecisionsDao->getAllDataForGivenEventTableUpload($uploadId, 'copyright_event', 'copyright');
    $this->heartbeat(1);
    $eccData = $this->allDecisionsDao->getAllDataForGivenTableUpload($tableName, 'ecc');
    $this->heartbeat(1);
    $eccDecisionData = $this->allDecisionsDao->getAllDataForGivenDecisionTableUpload($tableName, 'ecc_decision');
    $this->heartbeat(1);
    $eccEventData = $this->allDecisionsDao->getAllDataForGivenEventTableUpload($uploadId, 'ecc_event', 'ecc');
    $this->heartbeat(1);
    $ipraData = $this->allDecisionsDao->getAllDataForGivenTableUpload($tableName, 'ipra');
    $this->heartbeat(1);
    $ipraDecisionData = $this->allDecisionsDao->getAllDataForGivenDecisionTableUpload($tableName, 'ipra_decision');
    $this->heartbeat(1);
    $ipraEventData = $this->allDecisionsDao->getAllDataForGivenEventTableUpload($uploadId, 'ipra_event', 'ipra');
    $this->heartbeat(1);
    $reportInfoData = $this->uploadDao->getReportInfo($uploadId);
    $this->heartbeat(1);
    $licenseData = $this->allDecisionsDao->getAllLicenseDataForUpload($uploadId);
    $licenseData = $this->addExpressionMemberLicenseData($licenseData);
    $this->heartbeat(1);
    $mainLicenseData = $this->clearingDao->getMainLicenseIds($uploadId, $groupId);

    $contents = array(
      'pfile'=>$pfileData,
      'uploadtree'=>$uploadTreeData,
      'clearing_decision'=>$clearingDecisonData,
      'clearing_event'=>$clearingEventData,
      'clearing_decision_event'=>$clearingDecisonEventData,
      'license_ref_bulk'=>$licenseRefBulkData,
      'license_set_bulk'=>$licenseSetBulkData,
      'highlight_bulk'=>$bulkHighlightData,
      'copyright'=>$copyrightData,
      'copyright_decision'=>$copyrightDecisionData,
      'copyright_event'=>$copyrightEventData,
      'ecc'=>$eccData,
      'ecc_decision'=>$eccDecisionData,
      'ecc_event'=>$eccEventData,
      'ipra'=>$ipraData,
      'ipra_decision'=>$ipraDecisionData,
      'ipra_event'=>$ipraEventData,
      'report_info'=>$reportInfoData,
      'licenses'=>$licenseData,
      'upload_clearing_license'=>array_values($mainLicenseData)
    );

    $this->dropPfileTable($uploadId, $tableName);
    $this->writeReport($contents, $uploadId);

    return true;
  }

  /**
   * @brief Writes the data to a json file
   *
   * The file name is of format `FOSSology_Decisions_<packageName>_<d_m_Y_H_i_s>.json`.
   *
   * @param array $contents
   * @param int $uploadId
   */
  private function writeReport($contents, $uploadId)
  {
    global $SysConf;

    $packageName = $this->uploadDao->getUpload($uploadId)->getFilename();

    $fileBase = $SysConf['FOSSOLOGY']['path'] . "/report/";
    $fileName = $fileBase . "FOSSology_Decisions_" . $packageName . '_' . date("d_m_Y_H_i_s") . ".json";

    if (!is_dir($fileBase)) {
      mkdir($fileBase, 0777, true);
    }
    umask(0133);

    file_put_contents($fileName, json_encode($contents, JSON_UNESCAPED_SLASHES));

    $this->updateReportTable($uploadId, $this->jobId, $fileName);
  }

  /**
   * @brief Create database table.
   * @param int $uploadId
   * @param array $pfileData
   */
  private function insertPfileData($uploadId, $pfileData, $tableName)
  {
    $allPfileFk = array_keys($pfileData);
    foreach ($allPfileFk as $pfileFk) {
      $this->dbManager->insertInto($tableName, 'pfile_fk', array($pfileFk));
    }
  }

  /**
   * @brief Create database table.
   * @param int $uploadId
   */
  private function createPfileTable($uploadId, $tableName)
  {
    $this->dbManager->getSingleRow("CREATE TABLE IF NOT EXISTS ".$tableName." (pfile_fk BIGINT NOT NULL);",
      array(), __METHOD__);
  }

  /**
   * @brief Create database table.
   * @param int $uploadId
   */
  private function dropPfileTable($uploadId, $tableName)
  {
    $this->dbManager->getSingleRow("DROP TABLE IF EXISTS ".$tableName.";",
      array(), __METHOD__);
  }

  /**
   * @brief Update database with generated report path.
   * @param int $uploadId
   * @param int $jobId
   * @param string $filename
   */
  private function updateReportTable($uploadId, $jobId, $filename)
  {
    $this->dbManager->getSingleRow("INSERT INTO reportgen(upload_fk, job_fk, filepath) VALUES($1,$2,$3)",
      array($uploadId, $jobId, $filename), __METHOD__);
  }

  /**
   * Include normal license rows referenced inside exported expression ASTs.
   *
   * Expression AST leaf nodes store license_ref IDs. Importing the dump into a
   * different database requires those member licenses to be present in the dump
   * so the importer can remap the expression AST to the target database IDs.
   *
   * @param array $licenseData License rows already selected for the upload.
   * @return array License rows plus any expression member license rows.
   */
  private function addExpressionMemberLicenseData($licenseData)
  {
    $exportedLicenseIds = array();
    $expressionMemberIds = array();

    foreach ($licenseData as $licenseRow) {
      $exportedLicenseIds[intval($licenseRow['rf_pk'])] = true;
      if (!$this->isExpressionLicenseRow($licenseRow)) {
        continue;
      }
      $expressionAst = json_decode($licenseRow['rf_fullname'], true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        continue;
      }
      $this->collectExpressionMemberLicenseIds($expressionAst, $expressionMemberIds);
    }

    $missingLicenseIds = array();
    foreach (array_keys($expressionMemberIds) as $licenseId) {
      if (!array_key_exists($licenseId, $exportedLicenseIds)) {
        $missingLicenseIds[] = $licenseId;
      }
    }

    if (empty($missingLicenseIds)) {
      return $licenseData;
    }

    return array_merge($licenseData, $this->getLicenseDataForIds($missingLicenseIds));
  }

  /**
   * @param array $licenseRow Exported license row.
   * @return bool True for license expression rows.
   */
  private function isExpressionLicenseRow($licenseRow)
  {
    return array_key_exists('is_expression', $licenseRow)
      && ($licenseRow['is_expression'] === true || $licenseRow['is_expression'] === 't');
  }

  /**
   * Recursively collect numeric license IDs from an expression AST.
   *
   * @param array $node Expression AST node.
   * @param array $licenseIds Collected license IDs, keyed by ID.
   */
  private function collectExpressionMemberLicenseIds($node, &$licenseIds)
  {
    if (!is_array($node) || !array_key_exists('type', $node)) {
      return;
    }
    if ($node['type'] === 'License' && isset($node['value']) && is_numeric($node['value'])) {
      $licenseIds[intval($node['value'])] = true;
      return;
    }
    foreach (array('left', 'right', 'license', 'exception') as $childKey) {
      if (array_key_exists($childKey, $node)) {
        $this->collectExpressionMemberLicenseIds($node[$childKey], $licenseIds);
      }
    }
  }

  /**
   * Fetch export-compatible license rows for the given license IDs.
   *
   * @param array $licenseIds License IDs to export.
   * @return array Export-compatible license rows.
   */
  private function getLicenseDataForIds($licenseIds)
  {
    $licenseIds = array_values(array_unique(array_map('intval', $licenseIds)));
    if (empty($licenseIds)) {
      return array();
    }

    $params = array();
    $placeholders = array();
    foreach ($licenseIds as $licenseId) {
      $params[] = $licenseId;
      $placeholders[] = '$' . count($params);
    }

    $columns = "rf_pk, rf_shortname, rf_fullname, rf_text, rf_url, rf_notes, rf_md5, rf_risk";
    $sql = "WITH alllicense AS (" .
      "SELECT $columns, false AS is_candidate, false AS is_expression FROM ONLY license_ref UNION " .
      "SELECT $columns, true AS is_candidate, false AS is_expression FROM ONLY license_candidate) " .
      "SELECT * FROM alllicense WHERE rf_pk IN (" . implode(',', $placeholders) . ")";
    return $this->dbManager->getRows($sql, $params, __METHOD__ . ".expressionMemberLicenseData");
  }
}

$agent = new DecisionExporter();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
