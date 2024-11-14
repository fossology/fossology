<?php
/*
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 SPDX-FileCopyrightText: © 2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Decision Importer Data Creator class
 */

namespace Fossology\DecisionImporter;

use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Proxy\ScanJobProxy;
use UnexpectedValueException;

require_once "FoDecisionData.php";

/**
 * This class contains all the helper functions to create data from JSON report in database.
 */
class DecisionImporterDataCreator
{
  /**
   * @var DbManager $dbManager
   * DbManager object
   */
  private $dbManager;

  /**
   * @var AgentDao $agentDao
   * AgentDao object
   */
  private $agentDao;

  /**
   * @var CopyrightDao $copyrightDao
   * CopyrightDao object
   */
  private $copyrightDao;

  /**
   * @var LicenseDao $licenseDao
   * LicenseDao object
   */
  private $licenseDao;

  /**
   * @var UploadDao $uploadDao
   * UploadDao object
   */
  private $uploadDao;

  /**
   * @var ClearingDao $clearingDao
   * ClearingDao object
   */
  private $clearingDao;

  /**
   * @var int $userId
   * User ID to use while creating records.
   */
  private $userId;

  /**
   * @var int $groupId
   * Group ID to use while creating records.
   */
  private $groupId;

  /**
   * @var int $uploadId
   * Upload for which import is working.
   */
  private $uploadId;

  /**
   * @param DbManager $dbManager DbManager to use
   * @param AgentDao $agentDao AgentDao to use
   * @param CopyrightDao $copyrightDao CopyrightDao to use
   * @param LicenseDao $licenseDao LicenseDao to use
   * @param UploadDao $uploadDao UploadDao to use
   * @param ClearingDao $clearingDao ClearingDao to use
   */
  public function __construct(DbManager  $dbManager, AgentDao $agentDao, CopyrightDao $copyrightDao,
                              LicenseDao $licenseDao, UploadDao $uploadDao, ClearingDao $clearingDao)
  {
    $this->dbManager = $dbManager;
    $this->agentDao = $agentDao;
    $this->copyrightDao = $copyrightDao;
    $this->licenseDao = $licenseDao;
    $this->uploadDao = $uploadDao;
    $this->clearingDao = $clearingDao;
  }

  /**
   * @param int $userId
   */
  public function setUserId(int $userId): void
  {
    $this->userId = $userId;
  }

  /**
   * @param int $groupId
   */
  public function setGroupId(int $groupId): void
  {
    $this->groupId = $groupId;
  }

  /**
   * @param int $uploadId
   */
  public function setUploadId(int $uploadId): void
  {
    $this->uploadId = $uploadId;
  }

  /**
   * It creates the clearing decisions and events from the report.
   *
   * @param FoDecisionData $reportData The FoDecisionData object that contains the data to be imported.
   * @param DecisionImporterAgent $agentObj Agent object to send heartbeats
   */
  public function createClearingDecisions(FoDecisionData &$reportData,
                                          DecisionImporterAgent &$agentObj): void
  {
    $clearingDecisionList = $reportData->getClearingDecisionList();
    $clearingEventList = $reportData->getClearingEventList();
    $clearingDecisionEventList = $reportData->getClearingDecisionEventList();

    $i = 0;
    foreach ($clearingDecisionList as $oldDecisionId => $decisionItem) {
      if ($decisionItem["new_itemid"] !== null) {
        $newCdId = $this->dbManager->insertTableRow("clearing_decision", [
          "uploadtree_fk" => $decisionItem["new_itemid"],
          "pfile_fk" => $decisionItem["new_pfile"],
          "decision_type" => $decisionItem["decision_type"],
          "group_fk" => $this->groupId,
          "user_fk" => $this->userId,
          "scope" => $decisionItem["scope"],
          "date_added" => $decisionItem["date_added"]
        ], __METHOD__ . ".insertCd", "clearing_decision_pk");
      } else {
        $newCdId = null;
      }
      $clearingDecisionList[$oldDecisionId]["new_decision"] = $newCdId;
      $i++;
      if ($i == DecisionImporterAgent::$UPDATE_COUNT) {
        $agentObj->heartbeat(DecisionImporterAgent::$UPDATE_COUNT);
        $i = 0;
      }
    }
    if ($i != 0) {
      $agentObj->heartbeat($i);
    }

    $i = 0;
    foreach ($clearingEventList as $oldEventId => $eventItem) {
      if ($eventItem["new_itemid"] !== null) {
        $newCeId = $this->dbManager->insertTableRow("clearing_event", [
          "uploadtree_fk" => $eventItem["new_itemid"],
          "rf_fk" => $eventItem["new_rfid"],
          "removed" => $eventItem["removed"],
          "user_fk" => $this->userId,
          "group_fk" => $this->groupId,
          "job_fk" => null,
          "type_fk" => $eventItem["type_fk"],
          "comment" => $eventItem["comment"],
          "reportinfo" => $eventItem["reportinfo"],
          "acknowledgement" => $eventItem["acknowledgement"],
          "date_added" => $eventItem["date_added"]
        ], __METHOD__ . ".insertCe", "clearing_event_pk");
      } else {
        $newCeId = null;
      }
      $clearingEventList[$oldEventId]["new_event"] = $newCeId;
      $i++;
      if ($i == DecisionImporterAgent::$UPDATE_COUNT) {
        $agentObj->heartbeat(DecisionImporterAgent::$UPDATE_COUNT);
        $i = 0;
      }
    }
    if ($i != 0) {
      $agentObj->heartbeat($i);
    }

    foreach ($clearingDecisionEventList as $oldCdId => $ceList) {
      $newCdId = $clearingDecisionList[$oldCdId]["new_decision"];
      if ($newCdId === null) {
        continue;
      }
      foreach ($ceList as $oldCeId) {
        $newCeId = $clearingEventList[$oldCeId]["new_event"];
        if ($newCeId === null) {
          continue;
        }
        $this->dbManager->insertTableRow("clearing_decision_event", [
          "clearing_decision_fk" => $newCdId,
          "clearing_event_fk" => $newCeId
        ], __METHOD__ . ".insertCdCe");
      }
    }
    $agentObj->heartbeat(0);

    $reportData->setClearingDecisionList($clearingDecisionList)
      ->setClearingEventList($clearingEventList);
  }

  /**
   * Insert report conf data from report into the database
   *
   * @param FoDecisionData $reportData The report data object.
   */
  public function createReportData(FoDecisionData &$reportData): void
  {
    $reportInfo = $reportData->getReportInfo();
    $assocParams = [
      "upload_fk" => $this->uploadId,
      "ri_ga_checkbox_selection" => $reportInfo["ri_ga_checkbox_selection"],
      "ri_spdx_selection" => $reportInfo["ri_spdx_selection"],
      "ri_excluded_obligations" => $reportInfo["ri_excluded_obligations"],
      "ri_reviewed" => $reportInfo["ri_reviewed"],
      "ri_footer" => $reportInfo["ri_footer"],
      "ri_report_rel" => $reportInfo["ri_report_rel"],
      "ri_community" => $reportInfo["ri_community"],
      "ri_component" => $reportInfo["ri_component"],
      "ri_version" => $reportInfo["ri_version"],
      "ri_release_date" => $reportInfo["ri_release_date"],
      "ri_sw360_link" => $reportInfo["ri_sw360_link"],
      "ri_general_assesment" => $reportInfo["ri_general_assesment"],
      "ri_ga_additional" => $reportInfo["ri_ga_additional"],
      "ri_ga_risk" => $reportInfo["ri_ga_risk"],
      "ri_department" => $reportInfo["ri_department"],
      "ri_depnotes" => $reportInfo["ri_depnotes"],
      "ri_exportnotes" => $reportInfo["ri_exportnotes"],
      "ri_copyrightnotes" => $reportInfo["ri_copyrightnotes"],
      "ri_unifiedcolumns" => $reportInfo["ri_unifiedcolumns"],
      "ri_globaldecision" => $reportInfo["ri_globaldecision"],
      "ri_component_id" => $reportInfo["ri_component_id"],
      "ri_component_type" => $reportInfo["ri_component_type"]
    ];
    $existsSql = "SELECT ri_pk FROM report_info WHERE upload_fk = $1;";
    $existsStatement = __METHOD__ . ".reportInfoExists";

    if ($this->dbManager->getSingleRow($existsSql, [$this->uploadId], $existsStatement)) {
      $this->dbManager->updateTableRow("report_info", $assocParams, "upload_fk", $this->uploadId,
        __METHOD__ . ".updateReportInfo");
    } else {
      $this->dbManager->insertTableRow("report_info", $assocParams, __METHOD__ . ".insertReportInfo");
    }
  }

  /**
   * @brief Create copyright and sibling agent's related data in DB.
   *
   * Take the report data and create an entry for the agent in job queue. It then imports the agent data from report.
   * It then creates records for decisions and events.
   *
   * @param FoDecisionData $reportData The report data object.
   * @param DecisionImporterAgent $agentObj Agent object to send heartbeats
   * @param string $agentName The name of the agent. (can be "copyright", "ecc" or "ipra")
   * @param int $jobId Current job id
   */
  public function createCopyrightData(FoDecisionData &$reportData,
                                      DecisionImporterAgent &$agentObj,
                                      string $agentName, int $jobId): void
  {
    $type = $agentName;
    if ($agentName == "copyright") {
      $type = "statement";
    }
    $capitalizedAgentName = strtoupper(substr($agentName, 0, 1)) . substr($agentName, 1);
    $cxListMethod = "get" . $capitalizedAgentName . "List";
    $decisionListMethod = "get" . $capitalizedAgentName . "DecisionList";
    $eventListMethod = "get" . $capitalizedAgentName . "EventList";
    $cxList = $reportData->$cxListMethod();
    $decisionList = $reportData->$decisionListMethod();
    $eventList = $reportData->$eventListMethod();

    if (!$cxList and !$decisionList and !$eventList) {
      // No relevant data in the report - nothing to do
      return;
    }

    if (!$this->agentDao->arsTableExists($agentName)) {
      // FIXME This requires the user to manually run the respective agent to get past this point
      throw new UnexpectedValueException("No agent '$agentName' exists on server.");
    }
    $latestAgentId = $this->agentDao->getCurrentAgentId($agentName);
    $this->createCxJobs($agentName, $jobId, $latestAgentId);
    $cxExistSql = "SELECT " . $agentName . "_pk FROM $agentName WHERE pfile_fk = $1 AND agent_fk = $2 AND hash = $3;";
    $cxExistStatement = __METHOD__ . ".$agentName" . "Exist";

    $ceExistSql = "SELECT " . $agentName . "_event_pk FROM " . $agentName .
      "_event WHERE uploadtree_fk = $1 AND hash = $2 AND is_enabled = $3;";
    $ceExistStatement = __METHOD__ . ".$agentName" . "EventExist";

    $i = 0;
    foreach ($cxList as $oldId => $cItem) {
      $newCp = $this->dbManager->getSingleRow($cxExistSql, [
        $cItem["new_pfile"],
        $latestAgentId,
        $cItem["hash"]
      ], $cxExistStatement);
      if (empty($newCp)) {
        $newCp = $this->dbManager->insertTableRow($agentName, [
          "agent_fk" => $latestAgentId,
          "pfile_fk" => $cItem["new_pfile"],
          "content" => $cItem["content"],
          "type" => $type,
          "hash" => $cItem["hash"],
          "copy_startbyte" => $cItem["copy_startbyte"],
          "copy_endbyte" => $cItem["copy_endbyte"]
        ], __METHOD__ . ".insertCp." . $agentName, $agentName . "_pk");
      } else {
        $newCp = $newCp[$agentName . "_pk"];
      }
      $cxList[$oldId]["new_id"] = $newCp;
      $i++;
      if ($i == DecisionImporterAgent::$UPDATE_COUNT) {
        $agentObj->heartbeat(DecisionImporterAgent::$UPDATE_COUNT);
        $i = 0;
      }
    }
    if ($i != 0) {
      $agentObj->heartbeat($i);
    }

    $i = 0;
    foreach ($decisionList as $oldId => $decisionItem) {
      $newDecision = $this->copyrightDao->saveDecision($agentName . "_decision", $decisionItem['new_pfile'],
        $this->userId, $decisionItem['clearing_decision_type_fk'], $decisionItem['description'],
        $decisionItem['textfinding'], $decisionItem['comment']);
      $decisionList[$oldId]["new_id"] = $newDecision;
      $i++;
      if ($i == DecisionImporterAgent::$UPDATE_COUNT) {
        $agentObj->heartbeat(DecisionImporterAgent::$UPDATE_COUNT);
        $i = 0;
      }
    }
    if ($i != 0) {
      $agentObj->heartbeat($i);
    }

    $i = 0;
    foreach ($eventList as $eventItem) {
      if ($eventItem["new_itemid"] == null) {
        echo "ItemId of event hash " . $eventItem["hash"] . " is NULL\n";
        continue;
      }
      $ceId = $this->dbManager->getSingleRow($ceExistSql, [
        $eventItem["new_itemid"],
        $eventItem["hash"],
        $eventItem["is_enabled"]
      ], $ceExistStatement);
      if (empty($ceId)) {
        $this->dbManager->insertTableRow($agentName . "_event", [
          "upload_fk" => $this->uploadId,
          "uploadtree_fk" => $eventItem["new_itemid"],
          $agentName . "_fk" => $cxList[$eventItem["old_cpid"]]["new_id"],
          "content" => $eventItem["content"],
          "hash" => $eventItem["hash"],
          "is_enabled" => $eventItem["is_enabled"],
          "scope" => $eventItem["scope"]
        ], __METHOD__ . ".insertCe." . $agentName);
      }
      $i++;
      if ($i == DecisionImporterAgent::$UPDATE_COUNT) {
        $agentObj->heartbeat(DecisionImporterAgent::$UPDATE_COUNT);
        $i = 0;
      }
    }
    if ($i != 0) {
      $agentObj->heartbeat($i);
    }

    $cxListMethod = "set" . $capitalizedAgentName . "List";
    $reportData->$cxListMethod($cxList);
  }

  /**
   * It creates a job for the agent or return if it already exists. Thn marks it as completed, and then writes the agent
   * record to the database.
   *
   * @param string $agentName The name of the agent to create a job for.
   * @param int $currentJobId The job queue id of the current job.
   * @param int $agentId The latest agent ID of the agent you want to run.
   */
  private function createCxJobs(string $agentName, int $currentJobId, int $agentId): void
  {
    $scanJobProxy = new ScanJobProxy($this->agentDao, $this->uploadId);
    $scanJobProxy->createAgentStatus(array($agentName));
    $selectedScanners = $scanJobProxy->getLatestSuccessfulAgentIds();
    if (!empty($selectedScanners)) {
      return;
    }
    $markJobCompletedSql = "UPDATE jobqueue SET jq_end_bits = 1, jq_endtext = 'Completed', jq_endtime = NOW(), " .
      "jq_starttime = NOW() WHERE jq_pk = $1;";
    $markJobCompletedStatement = __METHOD__ . ".markComplete";

    $jqId = JobQueueAdd($currentJobId, $agentName, $this->uploadId, "no", NULL);
    $this->dbManager->getSingleRow($markJobCompletedSql, [$jqId], $markJobCompletedStatement);

    $arsId = $this->agentDao->writeArsRecord($agentName, $agentId, $this->uploadId);
    $this->agentDao->writeArsRecord($agentName, $agentId, $this->uploadId, $arsId, true);
  }

  /**
   * @brief Create monkbulk findings from the report.
   *
   * -# Create `license_ref_bulk` and `license_set_bulk` entries.
   * -# Create the job for monkbulk and deciderjob.
   * -# Update `clearing_event` entries with `job_fk`
   * -# Create `highlight_bulk` entries.
   * @param FoDecisionData $reportData
   * @param DecisionImporterAgent $agentObj
   */
  public function createMonkBulkData(FoDecisionData &$reportData,
                                     DecisionImporterAgent &$agentObj): void
  {
    $licenseRefBulkList = $reportData->getLicenseRefBulkList();
    $licenseSetBulkList = $reportData->getLicenseSetBulkList();
    $highlightBulkList = $reportData->getHighlightBulk();
    $clearingEventList = $reportData->getClearingEventList();

    // NOTE: Always run monkbulk on entire upload even if it was set for a folder, as it is done in reuser with bulk.
    // BulkReuser::rerunBulkAndDeciderOnUpload() ¯\_(ツ)_/¯
    $parentItem = $this->uploadDao->getUploadParent($this->uploadId);

    $i = 0;
    foreach ($licenseRefBulkList as $oldId => $licenseRefBulkItem) {
      $oldEventId = $this->getClearingEventForLrb($clearingEventList, $oldId);
      if (empty($oldEventId)) {
        echo "No clearing event for old LRB: $oldId\n";
        continue;
      }
      $licenseRemovals = [];
      foreach ($licenseSetBulkList[$oldId] as $licenseSetItem) {
        $licenseRemovals[$licenseSetItem["new_rfid"]] = [
          $licenseSetItem["removing"],
          $licenseSetItem["comment"],
          $licenseSetItem["reportinfo"],
          $licenseSetItem["acknowledgement"],
        ];
      }

      $newLrbId = $this->licenseDao->insertBulkLicense($this->userId, $this->groupId, $parentItem, $licenseRemovals,
        $licenseRefBulkItem["rf_text"], $licenseRefBulkItem["ignore_irrelevant"],
        $licenseRefBulkItem["bulk_delimiters"], $licenseRefBulkItem["scan_findings"]);
      $licenseRefBulkList[$oldId]["new_lrbid"] = $newLrbId;
      $jobId = $this->createMonkBulkJobs($newLrbId);
      $clearingEventList[$oldEventId]["new_lrbid"] = $newLrbId;
      $clearingEventList[$oldEventId]["job_fk"] = $jobId;
      $i++;
      if ($i == DecisionImporterAgent::$UPDATE_COUNT) {
        $agentObj->heartbeat(DecisionImporterAgent::$UPDATE_COUNT);
        $i = 0;
      }
    }
    if ($i != 0) {
      $agentObj->heartbeat($i);
    }

    $i = 0;
    foreach ($clearingEventList as $clearingEventItem) {
      if (array_key_exists("job_fk", $clearingEventItem)) {
        $assocParams = [
          "job_fk" => $clearingEventItem["job_fk"]
        ];
        $this->dbManager->updateTableRow("clearing_event", $assocParams, "clearing_event_pk",
          $clearingEventItem["new_event"], __METHOD__ . ".updateCeJob");
        $i++;
        if ($i == DecisionImporterAgent::$UPDATE_COUNT) {
          $agentObj->heartbeat(DecisionImporterAgent::$UPDATE_COUNT);
          $i = 0;
        }
      }
    }
    if ($i != 0) {
      $agentObj->heartbeat($i);
    }

    $i = 0;
    foreach ($highlightBulkList as $highlightBulkItem) {
      $assocParams = [
        "clearing_event_fk" => $clearingEventList[$highlightBulkItem["old_ceid"]]["new_event"],
        "lrb_fk" => $licenseRefBulkList[$highlightBulkItem["old_lrbid"]]["new_lrbid"],
        "start" => $highlightBulkItem["start"],
        "len" => $highlightBulkItem["len"]
      ];
      $this->dbManager->insertTableRow("highlight_bulk", $assocParams, __METHOD__ . ".insertHighlightBulk");
      $i++;
      if ($i == DecisionImporterAgent::$UPDATE_COUNT) {
        $agentObj->heartbeat(0);
        $i = 0;
      }
    }

    $reportData->setLicenseRefBulkList($licenseRefBulkList)
      ->setClearingEventList($clearingEventList);
  }

  /**
   * From the clearing event list, get the old clearing event id for a given license_ref_bulk id
   * @param array $clearingEventList
   * @param int $oldLrbId
   * @return int|null Old clearing event id if found, null otherwise.
   */
  private function getClearingEventForLrb(array $clearingEventList, int $oldLrbId)
  {
    foreach ($clearingEventList as $index => $clearingEventId) {
      if ($clearingEventId["old_lrbid"] == $oldLrbId) {
        return $index;
      }
    }
    return null;
  }

  /**
   * It creates a job and job queue for the monkbulk job agents. Then marks them as completed, and writes the agent ars
   * records to the database.
   * @param int $bulkId license_ref_bulk ID to create jobs for.
   * @return int Job id
   */
  private function createMonkBulkJobs(int $bulkId): int
  {
    $markJobCompletedSql = "UPDATE jobqueue SET jq_end_bits = 1, jq_endtext = 'Completed', jq_endtime = NOW(), " .
      "jq_starttime = NOW() WHERE jq_pk = $1;";
    $markJobCompletedStatement = __METHOD__ . ".markComplete";

    $upload = $this->uploadDao->getUpload($this->uploadId);
    $uploadName = $upload->getFilename();
    $latestDeciderAgentId = $this->agentDao->getCurrentAgentId("deciderjob");
    $latestMonkBulkAgentId = $this->agentDao->getCurrentAgentId("monkbulk");
    $jobId = JobAddJob($this->userId, $this->groupId, $uploadName, $this->uploadId);

    $monkbulkJobId = JobQueueAdd($jobId, "monkbulk", $bulkId, "no", null);
    $deciderJobId = JobQueueAdd($jobId, "deciderjob", $this->uploadId, "no", [$monkbulkJobId]);

    $monkbulkArsId = $this->agentDao->writeArsRecord("monkbulk", $latestMonkBulkAgentId, $this->uploadId);
    $this->agentDao->writeArsRecord("monkbulk", $latestDeciderAgentId, $this->uploadId, $monkbulkArsId, true);
    $this->dbManager->getSingleRow($markJobCompletedSql, [$monkbulkJobId], $markJobCompletedStatement);

    $deciderArsId = $this->agentDao->writeArsRecord("deciderjob", $latestDeciderAgentId, $this->uploadId);
    $this->agentDao->writeArsRecord("deciderjob", $latestDeciderAgentId, $this->uploadId, $deciderArsId, true);
    $this->dbManager->getSingleRow($markJobCompletedSql, [$deciderJobId], $markJobCompletedStatement);

    return $jobId;
  }

  /**
   * Create main license entries for the upload
   * @param FoDecisionData $reportData
   * @param int $uploadId
   * @param int $groupId
   */
  public function createMainLicenses(FoDecisionData &$reportData, int $uploadId, int $groupId)
  {
    $mainLicenseList = $reportData->getMainLicenseList();
    foreach ($mainLicenseList as $mainLicenseItem) {
      $this->clearingDao->makeMainLicense($uploadId, $groupId, $mainLicenseItem["new_rfid"]);
    }
  }
}
