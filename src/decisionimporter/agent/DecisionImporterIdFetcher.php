<?php
/*
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 SPDX-FileCopyrightText: © 2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Decision Importer Id Fetcher class
 */

namespace Fossology\DecisionImporter;

use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\PfileDao;
use Fossology\Lib\Db\DbManager;
use UnexpectedValueException;

require_once "FoDecisionData.php";

/**
 * Take the report JSON and translate old IDs to IDs from current Database.
 */
class DecisionImporterIdFetcher
{
  /** @var DbManager $dbManager */
  private $dbManager;

  /** @var PfileDao $pfileDao */
  private $pfileDao;

  /** @var LicenseDao $licenseDao */
  private $licenseDao;

  /**
   * @var int $groupId
   * Current group id
   */
  private $groupId;

  /**
   * @var int $userId
   * Current user id
   */
  private $userId;

  /**
   * @var int $uploadId
   * Current upload's id
   */
  private $uploadId;

  /**
   * @param DbManager $dbManager
   * @param PfileDao $pfileDao
   * @param LicenseDao $licenseDao
   */
  function __construct(DbManager $dbManager, PfileDao $pfileDao, LicenseDao $licenseDao)
  {
    $this->dbManager = $dbManager;
    $this->pfileDao = $pfileDao;
    $this->licenseDao = $licenseDao;
  }

  /**
   * @param int $groupId
   */
  public function setGroupId(int $groupId): void
  {
    $this->groupId = $groupId;
  }

  /**
   * @param int $userId
   */
  public function setUserId(int $userId): void
  {
    $this->userId = $userId;
  }

  /**
   * @param int $uploadId
   */
  public function setUploadId(int $uploadId): void
  {
    $this->uploadId = $uploadId;
  }

  /**
   * Update the IDs from report to IDs from DB.
   *
   * @param FoDecisionData $reportData The report data object.
   * @param DecisionImporterAgent $agentObj The agent object to send heartbeat.
   */
  public function getOrCreateIds(FoDecisionData &$reportData, DecisionImporterAgent &$agentObj): void
  {
    $pfileList = $reportData->getPfileList();
    $uploadTreeList = $reportData->getUploadtreeList();
    $clearingDecisionList = $reportData->getClearingDecisionList();
    $copyrightList = $reportData->getCopyrightList();
    $copyrightDecisionList = $reportData->getCopyrightDecisionList();
    $copyrightEventList = $reportData->getCopyrightEventList();
    $eccList = $reportData->getEccList();
    $eccDecisionList = $reportData->getEccDecisionList();
    $eccEventList = $reportData->getEccEventList();
    $ipraList = $reportData->getIpraList();
    $ipraDecisionList = $reportData->getIpraDecisionList();
    $ipraEventList = $reportData->getIpraEventList();
    $licenseList = $reportData->getLicensesList();
    $clearingEventList = $reportData->getClearingEventList();
    $licenseSetBulkList = $reportData->getLicenseSetBulkList();
    $mainLicenseList = $reportData->getMainLicenseList();

    $this->updatePfileIds($pfileList);
    $agentObj->heartbeat(0);
    $this->updateUploadTreeIds($uploadTreeList, $pfileList, $agentObj);
    $agentObj->heartbeat(0);
    $this->updateClearingDecision($clearingDecisionList, $uploadTreeList, $pfileList);
    $agentObj->heartbeat(0);
    $this->updateCxList($copyrightList, $pfileList);
    $agentObj->heartbeat(0);
    $this->updateDecisionList($copyrightDecisionList, $pfileList);
    $agentObj->heartbeat(0);
    $this->updateEventList($copyrightEventList, $uploadTreeList);
    $agentObj->heartbeat(0);
    $this->updateCxList($eccList, $pfileList);
    $agentObj->heartbeat(0);
    $this->updateDecisionList($eccDecisionList, $pfileList);
    $agentObj->heartbeat(0);
    $this->updateEventList($eccEventList, $uploadTreeList);
    $agentObj->heartbeat(0);
    $this->updateCxList($ipraList, $pfileList);
    $agentObj->heartbeat(0);
    $this->updateDecisionList($ipraDecisionList, $pfileList);
    $agentObj->heartbeat(0);
    $this->updateEventList($ipraEventList, $uploadTreeList);
    $agentObj->heartbeat(0);
    $this->updateLicenses($licenseList);
    $agentObj->heartbeat(0);
    $this->updateClearingEvent($clearingEventList, $uploadTreeList, $licenseList);
    $agentObj->heartbeat(0);
    $this->updateLicenseSetBulk($licenseSetBulkList, $licenseList);
    $agentObj->heartbeat(0);
    $this->updateMainLicenseList($mainLicenseList, $licenseList);
    $agentObj->heartbeat(0);

    $reportData->setPfileList($pfileList)
      ->setUploadtreeList($uploadTreeList)
      ->setClearingDecisionList($clearingDecisionList)
      ->setCopyrightList($copyrightList)
      ->setCopyrightDecisionList($copyrightDecisionList)
      ->setCopyrightEventList($copyrightEventList)
      ->setEccList($eccList)
      ->setEccDecisionList($eccDecisionList)
      ->setEccEventList($eccEventList)
      ->setIpraList($ipraList)
      ->setIpraDecisionList($ipraDecisionList)
      ->setIpraEventList($ipraEventList)
      ->setLicensesList($licenseList)
      ->setClearingEventList($clearingEventList)
      ->setLicenseSetBulkList($licenseSetBulkList)
      ->setMainLicenseList($mainLicenseList);
  }

  /**
   * Update pfile id from database using pfile hash.
   * @param array $pfileList
   */
  private function updatePfileIds(array &$pfileList): void
  {
    foreach ($pfileList as $oldId => $item) {
      $hash = $item["hash"];
      $hashList = explode(".", $item["hash"]);
      $pfile = $this->pfileDao->getPfile($hashList[0], $hashList[1], null, $hashList[2]);
      if ($pfile == null) {
        echo "Can't find pfile with hash '$hash' in DB";
      }
      $pfileList[$oldId]["new_pfile"] = $pfile['pfile_pk'];
    }
  }

  /**
   * Update upload tree ids using pfile, lft and rgt
   * @param array $uploadTreeList
   * @param array $pfileList
   * @param DecisionImporterAgent $agentObj Agent object to send heartbeats
   */
  private function updateUploadTreeIds(array &$uploadTreeList, array $pfileList,
                                       DecisionImporterAgent &$agentObj): void
  {
    $sqlAllTree = "SELECT * FROM uploadtree WHERE upload_fk = $1 AND ufile_mode & (1<<28) = 0;";
    $statementAllTree = __METHOD__ . ".allTree";
    $allUploadTree = $this->dbManager->getRows($sqlAllTree, [$this->uploadId], $statementAllTree);
    $i = 0;
    foreach ($uploadTreeList as $oldItemId => $item) {
      $new_pfile = $pfileList[$item["old_pfile"]]["new_pfile"];
      $matchIndex = -INF;
      $j = 0;
      foreach ($allUploadTree as $index => $uploadTreeItem) {
        if ($uploadTreeItem["pfile_fk"] == $new_pfile) {
          if (array_key_exists("path", $item)) {
            $newpath = Dir2Path($uploadTreeItem["uploadtree_pk"]);
            $newpath = implode("/", array_column($newpath, "ufile_name"));
            if ($newpath == $item["path"]) {
              $matchIndex = $index;
              break;
            }
          } elseif ($uploadTreeItem["lft"] == $item["lft"] && $uploadTreeItem["rgt"] == $item["rgt"]) {
            $matchIndex = $index;
            break;
          }
        }
        $j++;
        if ($j == DecisionImporterAgent::$UPDATE_COUNT) {
          $agentObj->heartbeat(0);
          $j = 0;
        }
      }
      if ($matchIndex == -INF) {
        $path = $oldItemId;
        if (array_key_exists("path", $item)) {
          $path = $item["path"];
        }
        echo "Can't find item with pfile '$new_pfile' in upload " .
          "'$this->uploadId'.\nIgnoring: $path";
        $uploadTreeList[$oldItemId]["new_itemid"] = null;
      } else {
        $uploadTreeList[$oldItemId]["new_itemid"] = $allUploadTree[$matchIndex]["uploadtree_pk"];
      }
      $i++;
      if ($i == DecisionImporterAgent::$UPDATE_COUNT) {
        $agentObj->heartbeat(0);
        $i = 0;
      }
    }
  }

  /**
   * Update pfile id and utree id for clearing decisions.
   * @param array $clearingDecisionList
   * @param array $uploadTreeList
   * @param array $pfileList
   */
  private function updateClearingDecision(array &$clearingDecisionList, array $uploadTreeList, array $pfileList): void
  {
    foreach ($clearingDecisionList as $index => $item) {
      $newItemId = $uploadTreeList[$item["old_itemid"]]["new_itemid"];
      $newPfileId = $pfileList[$item["old_pfile"]]["new_pfile"];
      $clearingDecisionList[$index]["new_itemid"] = $newItemId;
      $clearingDecisionList[$index]["new_pfile"] = $newPfileId;
    }
  }

  /**
   * Update pfile id for copyright and sibling agents.
   * @param array $cxList
   * @param array $pfileList
   */
  private function updateCxList(array &$cxList, array $pfileList): void
  {
    foreach ($cxList as $index => $item) {
      $newPfileId = $pfileList[$item["old_pfile"]]["new_pfile"];
      $cxList[$index]["new_pfile"] = $newPfileId;
    }
  }

  /**
   * Update pfile id for decisions in copyright and sibling agents.
   * @param array $decisionList
   * @param array $pfileList
   */
  private function updateDecisionList(array &$decisionList, array $pfileList): void
  {
    foreach ($decisionList as $index => $decisionItem) {
      $newPfileId = $pfileList[$decisionItem["old_pfile"]]["new_pfile"];
      $decisionList[$index]["new_pfile"] = $newPfileId;
    }
  }

  /**
   * Update utree id for events in copyright and sibling agents.
   * @param array $eventList
   * @param array $uploadtreeList
   */
  private function updateEventList(array &$eventList, array $uploadtreeList): void
  {
    foreach ($eventList as $index => $eventItem) {
      if (!array_key_exists($eventItem["old_itemid"], $uploadtreeList)) {
        echo "Unable to find item id for old_id: " . $eventItem["old_itemid"] . "\n";
        $newItemId = null;
      } else {
        $newItemId = $uploadtreeList[$eventItem["old_itemid"]]["new_itemid"];
      }
      $eventList[$index]["new_itemid"] = $newItemId;
    }
  }

  /**
   * Update license id if shortname exists in DB otherwise create a new license.
   * @param array $licenseList
   */
  private function updateLicenses(array &$licenseList): void
  {
    foreach ($licenseList as $index => $item) {
      $newLicenseId = null;
      $license = $this->licenseDao->getLicenseByShortName($item["rf_shortname"], $this->groupId);
      if ($license == null) {
        $newLicenseData = [
          "rf_fullname" => $item["rf_fullname"],
          "rf_url" => $item["rf_url"],
          "rf_notes" => $item["rf_notes"],
          "rf_risk" => $item["rf_risk"],
          "rf_md5" => $item["rf_md5"],
        ];
        if ($item["is_candidate"] == "t") {
          $newLicenseId = $this->licenseDao->insertUploadLicense($item["rf_shortname"], $item["rf_text"],
            $this->groupId, $this->userId);
        } else {
          $newLicenseId = $this->licenseDao->insertLicense($item["rf_shortname"], $item["rf_text"]);
        }
        $this->dbManager->updateTableRow("license_ref", $newLicenseData, "rf_pk", $newLicenseId);
      } else {
        $newLicenseId = $license->getId();
      }
      $licenseList[$index]["new_rfid"] = $newLicenseId;
    }
  }

  /**
   * Update pfile id and license id for clearing events.
   * @param array $clearingEventList
   * @param array $uploadTreeList
   * @param array $licenseList
   */
  private function updateClearingEvent(array &$clearingEventList, array $uploadTreeList, array $licenseList): void
  {
    foreach ($clearingEventList as $index => $item) {
      $newItemId = $uploadTreeList[$item["old_itemid"]]["new_itemid"];
      $newLicenseId = $licenseList[$item["old_rfid"]]["new_rfid"];
      $clearingEventList[$index]["new_itemid"] = $newItemId;
      $clearingEventList[$index]["new_rfid"] = $newLicenseId;
    }
  }

  /**
   * Update license ID in license set bulk
   * @param array $licenseSetBulkList
   * @param array $licenseList
   */
  private function updateLicenseSetBulk(array &$licenseSetBulkList, array $licenseList): void
  {
    foreach ($licenseSetBulkList as $lrbId => $licenseSetBulks) {
      foreach ($licenseSetBulks as $index => $licenseSetBulkItem) {
        $newRfId = $licenseList[$licenseSetBulkItem["old_rfid"]]["new_rfid"];
        $licenseSetBulkList[$lrbId][$index]["new_rfid"] = $newRfId;
      }
    }
  }

  /**
   * Update license ID in main license list
   * @param array $mainLicenseList
   * @param array $licenseList
   */
  private function updateMainLicenseList(array &$mainLicenseList, array $licenseList): void
  {
    foreach ($mainLicenseList as $oldId => $mainLicenseItem) {
      $newLicenseId = $licenseList[$mainLicenseItem["old_rfid"]]["new_rfid"];
      $mainLicenseList[$oldId]["new_rfid"] = $newLicenseId;
    }
  }
}
