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
    if (!empty($pfileData)) {
      $this->createPfileTable($uploadId, $tableName);
    }
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
    $reportInfoData = $this->uploadDao->getReportInfo($uploadId);
    $this->heartbeat(1);
    $licenseData = $this->allDecisionsDao->getAllLicenseDataForUpload($uploadId);
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
      'report_info'=>$reportInfoData,
      'licenses'=>$licenseData,
      'upload_clearing_license'=>array_values($mainLicenseData)
    );
    if (!empty($pfileData)) {
      $this->dropPfileTable($uploadId, $tableName);
    }
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
}

$agent = new DecisionExporter();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
