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
   * Update upload tree ids using content-centric matching.
   * Files are matched by content (pfile) - applies decisions to all files with matching content.
   * When multiple files have identical content, decisions are applied to all of them.
   *
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

    $pfileToUploadTree = [];
    foreach ($allUploadTree as $uploadTreeItem) {
      $pfileFk = $uploadTreeItem["pfile_fk"];
      if (!isset($pfileToUploadTree[$pfileFk])) {
        $pfileToUploadTree[$pfileFk] = [];
      }
      $pfileToUploadTree[$pfileFk][] = $uploadTreeItem;
    }

    $i = 0;
    foreach ($uploadTreeList as $oldItemId => $item) {
      $new_pfile = $pfileList[$item["old_pfile"]]["new_pfile"];

      if (!isset($pfileToUploadTree[$new_pfile]) || empty($pfileToUploadTree[$new_pfile])) {
        $path = $oldItemId;
        if (array_key_exists("path", $item)) {
          $path = $item["path"];
        }
        echo "No file with matching content (pfile: $new_pfile) found in upload $this->uploadId.\n";
        echo "Original path was: $path\n";
        $uploadTreeList[$oldItemId]["new_itemid"] = null;
        continue;
      }

      $matchingItems = $pfileToUploadTree[$new_pfile];

      if (count($matchingItems) > 1) {
        echo "Multiple files (" . count($matchingItems) . ") have identical content (pfile: $new_pfile).\n";
        if (array_key_exists("path", $item)) {
          echo "Original path was: " . $item["path"] . "\n";
        }
        echo "Applying decisions to all matching files.\n";

        $matchingItemIds = [];
        foreach ($matchingItems as $matchingItem) {
          $matchingItemIds[] = $matchingItem["uploadtree_pk"];
        }
        $uploadTreeList[$oldItemId]["new_itemid"] = $matchingItemIds;
      } else {
        $matchingItem = $matchingItems[0];
        $uploadTreeList[$oldItemId]["new_itemid"] = $matchingItem["uploadtree_pk"];
      }

      if (count($matchingItems) == 1 && array_key_exists("path", $item)) {
        $matchingItem = $matchingItems[0];
        $newpath = Dir2Path($matchingItem["uploadtree_pk"]);
        $newpath = implode("/", array_column($newpath, "ufile_name"));
        if ($newpath != $item["path"]) {
          echo "Info: File content matched but path changed.\n";
          echo "  Old path: " . $item["path"] . "\n";
          echo "  New path: " . $newpath . "\n";
          echo "  Decision will be applied based on content match.\n";
        }
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
    $expandedDecisions = [];
    foreach ($clearingDecisionList as $index => $item) {
      $newItemId = $uploadTreeList[$item["old_itemid"]]["new_itemid"];
      $newPfileId = $pfileList[$item["old_pfile"]]["new_pfile"];

      if (is_array($newItemId)) {
        foreach ($newItemId as $itemId) {
          $expandedDecision = $item;
          $expandedDecision["new_itemid"] = $itemId;
          $expandedDecision["new_pfile"] = $newPfileId;
          $expandedDecision["original_index"] = $index;
          $expandedDecisions[] = $expandedDecision;
        }
      } else {
        $clearingDecisionList[$index]["new_itemid"] = $newItemId;
        $clearingDecisionList[$index]["new_pfile"] = $newPfileId;
        $clearingDecisionList[$index]["original_index"] = $index;
        $expandedDecisions[] = $clearingDecisionList[$index];
      }
    }
    $clearingDecisionList = $expandedDecisions;
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
    $expandedEvents = [];
    foreach ($eventList as $index => $eventItem) {
      if (!array_key_exists($eventItem["old_itemid"], $uploadtreeList)) {
        echo "Unable to find item id for old_id: " . $eventItem["old_itemid"] . "\n";
        $newItemId = null;
        $eventList[$index]["new_itemid"] = $newItemId;
        $expandedEvents[] = $eventList[$index];
      } else {
        $newItemId = $uploadtreeList[$eventItem["old_itemid"]]["new_itemid"];

        if (is_array($newItemId)) {
          foreach ($newItemId as $itemId) {
            $expandedEvent = $eventItem;
            $expandedEvent["new_itemid"] = $itemId;
            $expandedEvents[] = $expandedEvent;
          }
        } else {
          $eventList[$index]["new_itemid"] = $newItemId;
          $expandedEvents[] = $eventList[$index];
        }
      }
    }
    $eventList = $expandedEvents;
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
    $expandedEvents = [];
    foreach ($clearingEventList as $index => $item) {
      $newItemId = $uploadTreeList[$item["old_itemid"]]["new_itemid"];

      $newLicenseId = null;
      if (isset($item["old_rfid"]) && $item["old_rfid"] != -1 && isset($licenseList[$item["old_rfid"]])) {
        $newLicenseId = $licenseList[$item["old_rfid"]]["new_rfid"];
      } else {
        echo "Info: Clearing event without license reference (old_rfid: " . ($item["old_rfid"] ?? 'null') . ") - using null license.\n";
      }

      if (is_array($newItemId)) {
        foreach ($newItemId as $itemId) {
          $expandedEvent = $item;
          $expandedEvent["new_itemid"] = $itemId;
          $expandedEvent["new_rfid"] = $newLicenseId;
          $expandedEvent["original_index"] = $index;
          $expandedEvents[] = $expandedEvent;
        }
      } else {
        $clearingEventList[$index]["new_itemid"] = $newItemId;
        $clearingEventList[$index]["new_rfid"] = $newLicenseId;
        $clearingEventList[$index]["original_index"] = $index;
        $expandedEvents[] = $clearingEventList[$index];
      }
    }
    $clearingEventList = $expandedEvents;
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
        if (!isset($licenseSetBulkItem["old_rfid"]) || $licenseSetBulkItem["old_rfid"] == -1 || !isset($licenseList[$licenseSetBulkItem["old_rfid"]])) {
          echo "Warning: Invalid or missing license reference (old_rfid: " . ($licenseSetBulkItem["old_rfid"] ?? 'null') . ") in license set bulk. Skipping.\n";
          continue;
        }
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
      if (!isset($mainLicenseItem["old_rfid"]) || $mainLicenseItem["old_rfid"] == -1 || !isset($licenseList[$mainLicenseItem["old_rfid"]])) {
        echo "Warning: Invalid or missing license reference (old_rfid: " . ($mainLicenseItem["old_rfid"] ?? 'null') . ") in main license list. Skipping.\n";
        unset($mainLicenseList[$oldId]);
        continue;
      }
      $newLicenseId = $licenseList[$mainLicenseItem["old_rfid"]]["new_rfid"];
      $mainLicenseList[$oldId]["new_rfid"] = $newLicenseId;
    }
  }
}