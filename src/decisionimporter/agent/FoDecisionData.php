<?php
/*
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 SPDX-FileCopyrightText: © 2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief FoDecisionData class
 */

namespace Fossology\DecisionImporter;

/**
 * Class to hold data from decision JSON report.
 */
class FoDecisionData
{
  /** @var array $pfileList */
  private $pfileList;

  /** @var array $uploadtreeList */
  private $uploadtreeList;

  /** @var array $clearing_decisionList */
  private $clearing_decisionList;

  /** @var array $clearing_eventList */
  private $clearing_eventList;

  /** @var array $clearing_decision_eventList */
  private $clearing_decision_eventList;

  /** @var array $license_ref_bulkList */
  private $license_ref_bulkList;

  /** @var array $license_set_bulkList */
  private $license_set_bulkList;

  /** @var array $highlight_bulk */
  private $highlight_bulk;

  /** @var array $copyrightList */
  private $copyrightList;

  /** @var array $copyright_decisionList */
  private $copyright_decisionList;

  /** @var array $copyright_eventList */
  private $copyright_eventList;

  /** @var array $eccList */
  private $eccList;

  /** @var array $ecc_decisionList */
  private $ecc_decisionList;

  /** @var array $ecc_eventList */
  private $ecc_eventList;

  /** @var array $ipraList */
  private $ipraList;

  /** @var array $ipra_decisionList */
  private $ipra_decisionList;

  /** @var array $ipra_eventList */
  private $ipra_eventList;

  /** @var array $report_info */
  private $report_info;

  /** @var array $licensesList */
  private $licensesList;

  /** @var array $mainLicenseList */
  private $mainLicenseList;

  private function __construct()
  {
  }

  /**
   * Create class object for the given report path.
   * @param string $path Path to JSON report.
   * @return FoDecisionData Object will all data filled.
   */
  public static function createFromFile(string $path): FoDecisionData
  {
    $decisionData = new self();
    $data = json_decode(file_get_contents($path), true);
    $decisionData->insertPfileList($data['pfile'])
      ->insertUploadtreeList($data['uploadtree'])
      ->insertClearingDecisionList($data['clearing_decision'])
      ->insertClearingEventList($data['clearing_event'])
      ->insertClearingDecisionEventList($data['clearing_decision_event'])
      ->insertLicenseRefBulk($data['license_ref_bulk'])
      ->insertLicenseSetBulk($data['license_set_bulk'])
      ->insertHighlightBulk($data['highlight_bulk'])
      ->insertCopyrightList($data['copyright'])
      ->insertCopyrightDecisionList($data['copyright_decision'])
      ->insertCopyrightEventList($data['copyright_event'])
      ->insertEccList($data['ecc'])
      ->insertEccDecisionList($data['ecc_decision'])
      ->insertEccEventList($data['ecc_event'])
      ->insertIpraList($data['ipra'])
      ->insertIpraDecisionList($data['ipra_decision'])
      ->insertIpraEventList($data['ipra_event'])
      ->insertReportInfo($data['report_info'])
      ->insertLicensesList($data['licenses'])
      ->insertMainListList($data['upload_clearing_license']);
    return $decisionData;
  }

  /**
   * @param array $mainLicenseList
   * @return FoDecisionData
   */
  private function insertMainListList(array $mainLicenseList): FoDecisionData
  {
    $this->mainLicenseList = [];
    foreach ($mainLicenseList as $mainLicenseItem) {
      $this->mainLicenseList[$mainLicenseItem] = [
        "old_rfid" => $mainLicenseItem
      ];
    }
    return $this;
  }

  /**
   * @param array $licensesList
   * @return FoDecisionData
   */
  private function insertLicensesList(array $licensesList): FoDecisionData
  {
    $this->licensesList = [];
    foreach ($licensesList as $licenseItem) {
      $this->licensesList[$licenseItem['rf_pk']] = $licenseItem;
    }
    return $this;
  }

  /**
   * @param array $report_info
   * @return FoDecisionData
   */
  private function insertReportInfo(array $report_info): FoDecisionData
  {
    $this->report_info = $report_info;
    return $this;
  }

  /**
   * @param array $ipra_eventList
   * @return FoDecisionData
   */
  private function insertIpraEventList(array $ipra_eventList): FoDecisionData
  {
    $this->ipra_eventList = [];
    foreach ($ipra_eventList as $ipraEventItem) {
      $this->ipra_eventList[] = $this->createEventItem($ipraEventItem, "ipra");
    }
    return $this;
  }

  /**
   * @param array $eventItemData
   * @param string $agentName
   * @return array
   */
  private function createEventItem(array $eventItemData, string $agentName): array
  {
    return [
      "old_cpid" => $eventItemData[$agentName . '_fk'],
      "old_itemid" => $eventItemData['uploadtree_fk'],
      "content" => $eventItemData['content'],
      "hash" => $eventItemData['hash'],
      "is_enabled" => $eventItemData['is_enabled'],
      "scope" => $eventItemData['scope'],
    ];
  }

  /**
   * @param array $ipra_decisionList
   * @return FoDecisionData
   */
  private function insertIpraDecisionList(array $ipra_decisionList): FoDecisionData
  {
    $this->ipra_decisionList = [];
    foreach ($ipra_decisionList as $ipraDecisionItem) {
      $this->ipra_decisionList[$ipraDecisionItem['ipra_decision_pk']] =
        $this->createDecisionItem($ipraDecisionItem);
    }
    return $this;
  }

  /**
   * @param array $decisionItemData
   * @return array
   */
  private function createDecisionItem(array $decisionItemData): array
  {
    return [
      "old_pfile" => $decisionItemData['pfile_fk'],
      "clearing_decision_type_fk" => $decisionItemData['clearing_decision_type_fk'],
      "description" => $decisionItemData['description'],
      "textfinding" => $decisionItemData['textfinding'],
      "hash" => $decisionItemData['hash'],
      "comment" => $decisionItemData['comment'],
    ];
  }

  /**
   * @param array $ipraList
   * @return FoDecisionData
   */
  private function insertIpraList(array $ipraList): FoDecisionData
  {
    $this->ipraList = [];
    foreach ($ipraList as $ipraItem) {
      $this->ipraList[$ipraItem['ipra_pk']] = $this->createCxItem($ipraItem);
    }
    return $this;
  }

  /**
   * @param array $cxItemData
   * @return array
   */
  private function createCxItem(array $cxItemData): array
  {
    return [
      "old_pfile" => $cxItemData['pfile_fk'],
      "content" => $cxItemData['content'],
      "hash" => $cxItemData['hash'],
      "copy_startbyte" => $cxItemData['copy_startbyte'],
      "copy_endbyte" => $cxItemData['copy_endbyte'],
    ];
  }

  /**
   * @param array $ecc_eventList
   * @return FoDecisionData
   */
  private function insertEccEventList(array $ecc_eventList): FoDecisionData
  {
    $this->ecc_eventList = [];
    foreach ($ecc_eventList as $eccEventItem) {
      $this->ecc_eventList[] = $this->createEventItem($eccEventItem, "ecc");
    }
    return $this;
  }

  /**
   * @param array $ecc_decisionList
   * @return FoDecisionData
   */
  private function insertEccDecisionList(array $ecc_decisionList): FoDecisionData
  {
    $this->ecc_decisionList = [];
    foreach ($ecc_decisionList as $eccDecisionItem) {
      $this->ecc_decisionList[$eccDecisionItem['ecc_decision_pk']] = $this->createDecisionItem($eccDecisionItem);
    }
    return $this;
  }

  /**
   * @param array $eccList
   * @return FoDecisionData
   */
  private function insertEccList(array $eccList): FoDecisionData
  {
    $this->eccList = [];
    foreach ($eccList as $eccItem) {
      $this->eccList[$eccItem['ecc_pk']] = $this->createCxItem($eccItem);
    }
    return $this;
  }

  /**
   * @param array $copyright_eventList
   * @return FoDecisionData
   */
  private function insertCopyrightEventList(array $copyright_eventList): FoDecisionData
  {
    $this->copyright_eventList = [];
    foreach ($copyright_eventList as $copyrightEventItem) {
      $this->copyright_eventList[] = $this->createEventItem($copyrightEventItem, "copyright");
    }
    return $this;
  }

  /**
   * @param array $copyright_decisionList
   * @return FoDecisionData
   */
  private function insertCopyrightDecisionList(array $copyright_decisionList): FoDecisionData
  {
    $this->copyright_decisionList = [];
    foreach ($copyright_decisionList as $copyrightDecisionItem) {
      $this->copyright_decisionList[$copyrightDecisionItem['copyright_decision_pk']] =
        $this->createDecisionItem($copyrightDecisionItem);
    }
    return $this;
  }

  /**
   * @param array $copyrightList
   * @return FoDecisionData
   */
  private function insertCopyrightList(array $copyrightList): FoDecisionData
  {
    $this->copyrightList = [];
    foreach ($copyrightList as $copyrightItem) {
      $this->copyrightList[$copyrightItem['copyright_pk']] = $this->createCxItem($copyrightItem);
    }
    return $this;
  }

  /**
   * @param array $highlightBulkList
   * @return FoDecisionData
   */
  private function insertHighlightBulk(array $highlightBulkList): FoDecisionData
  {
    $this->highlight_bulk = [];
    foreach ($highlightBulkList as $highlightBulkItem) {
      $this->highlight_bulk[] = [
        "old_ceid" => $highlightBulkItem['clearing_event_fk'],
        "old_lrbid" => $highlightBulkItem['lrb_fk'],
        "start" => $highlightBulkItem['start'],
        "len" => $highlightBulkItem['len'],
      ];
    }
    return $this;
  }

  /**
   * @param array $licenseSetBulkList
   * @return FoDecisionData
   */
  private function insertLicenseSetBulk(array $licenseSetBulkList): FoDecisionData
  {
    $this->license_set_bulkList = [];
    foreach ($licenseSetBulkList as $licenseSetBulkItem) {
      if (!array_key_exists($licenseSetBulkItem["lrb_fk"], $this->license_set_bulkList)) {
        $this->license_set_bulkList[$licenseSetBulkItem["lrb_fk"]] = [];
      }
      $this->license_set_bulkList[$licenseSetBulkItem["lrb_fk"]][] = [
        "old_rfid" => $licenseSetBulkItem['rf_fk'],
        "removing" => $licenseSetBulkItem['removing'] == "t",
        "comment" => $licenseSetBulkItem['comment'],
        "reportinfo" => $licenseSetBulkItem['reportinfo'],
        "acknowledgement" => $licenseSetBulkItem['acknowledgement'],
      ];
    }
    return $this;
  }

  /**
   * @param array $licenseRefBulkList
   * @return FoDecisionData
   */
  private function insertLicenseRefBulk(array $licenseRefBulkList): FoDecisionData
  {
    $this->license_ref_bulkList = [];
    foreach ($licenseRefBulkList as $licenseRefBulkItem) {
      $this->license_ref_bulkList[$licenseRefBulkItem["lrb_pk"]] = [
        "rf_text" => $licenseRefBulkItem['rf_text'],
        "old_itemid" => $licenseRefBulkItem['uploadtree_fk'],
        "ignore_irrelevant" => $licenseRefBulkItem['ignore_irrelevant'] == "t",
        "bulk_delimiters" => $licenseRefBulkItem['bulk_delimiters'],
        "scan_findings" => $licenseRefBulkItem['scan_findings'],
      ];
    }
    return $this;
  }

  /**
   * @param array $clearing_decision_eventList
   * @return FoDecisionData
   */
  private function insertClearingDecisionEventList(array $clearing_decision_eventList): FoDecisionData
  {
    $this->clearing_decision_eventList = [];
    foreach ($clearing_decision_eventList as $clearingDecisionEventItem) {
      if (!array_key_exists($clearingDecisionEventItem['clearing_decision_fk'], $this->clearing_decision_eventList)) {
        $this->clearing_decision_eventList[$clearingDecisionEventItem['clearing_decision_fk']] = [];
      }
      $this->clearing_decision_eventList[$clearingDecisionEventItem['clearing_decision_fk']][] =
        $clearingDecisionEventItem['clearing_event_fk'];
    }
    return $this;
  }

  /**
   * @param array $clearing_eventList
   * @return FoDecisionData
   */
  private function insertClearingEventList(array $clearing_eventList): FoDecisionData
  {
    $this->clearing_eventList = [];
    foreach ($clearing_eventList as $clearingEventItem) {
      $this->clearing_eventList[$clearingEventItem['clearing_event_pk']] = [
        "old_itemid" => $clearingEventItem['uploadtree_fk'],
        "old_rfid" => $clearingEventItem['rf_fk'],
        "removed" => $clearingEventItem['removed'],
        "old_lrbid" => $clearingEventItem['lrb_pk'],
        "type_fk" => $clearingEventItem['type_fk'],
        "comment" => $clearingEventItem['comment'],
        "reportinfo" => $clearingEventItem['reportinfo'],
        "acknowledgement" => $clearingEventItem['acknowledgement'],
        "date_added" => $clearingEventItem['date_added'],
      ];
    }
    return $this;
  }

  /**
   * @param array $clearing_decisionList
   * @return FoDecisionData
   */
  private function insertClearingDecisionList(array $clearing_decisionList): FoDecisionData
  {
    $this->clearing_decisionList = [];
    foreach ($clearing_decisionList as $clearingDecisionItem) {
      $this->clearing_decisionList[$clearingDecisionItem['clearing_decision_pk']] = [
        "old_itemid" => $clearingDecisionItem['uploadtree_fk'],
        "old_pfile" => $clearingDecisionItem['pfile_fk'],
        "decision_type" => $clearingDecisionItem['decision_type'],
        "scope" => $clearingDecisionItem['scope'],
        "date_added" => $clearingDecisionItem['date_added'],
      ];
    }
    return $this;
  }

  /**
   * @param array $uploadtreeList
   * @return FoDecisionData
   */
  private function insertUploadtreeList(array $uploadtreeList): FoDecisionData
  {
    $this->uploadtreeList = [];
    foreach ($uploadtreeList as $uploadTreeItem) {
      $this->uploadtreeList[$uploadTreeItem['uploadtree_pk']] = [
        "old_pfile" => $uploadTreeItem['pfile_fk'],
        "lft" => $uploadTreeItem['lft'],
        "rgt" => $uploadTreeItem['rgt'],
        "path" => array_key_exists("path", $uploadTreeItem) ? $uploadTreeItem["path"] : ""
      ];
    }
    return $this;
  }

  /**
   * @param array $pfileList
   * @return FoDecisionData
   */
  private function insertPfileList(array $pfileList): FoDecisionData
  {
    $this->pfileList = [];
    foreach ($pfileList as $oldId => $hash) {
      $this->pfileList[$oldId] = ["hash" => $hash];
    }
    return $this;
  }

  /**
   * @return array
   */
  public function getMainLicenseList(): array
  {
    return $this->mainLicenseList;
  }

  /**
   * @param array $mainLicenseList
   * @return FoDecisionData
   */
  public function setMainLicenseList(array $mainLicenseList): FoDecisionData
  {
    $this->mainLicenseList = $mainLicenseList;
    return $this;
  }

  /**
   * @return array
   */
  public function getHighlightBulk(): array
  {
    return $this->highlight_bulk;
  }

  /**
   * @param array $highlight_bulk
   * @return FoDecisionData
   */
  public function setHighlightBulk(array $highlight_bulk): FoDecisionData
  {
    $this->highlight_bulk = $highlight_bulk;
    return $this;
  }

  /**
   * @return array
   */
  public function getPfileList(): array
  {
    return $this->pfileList;
  }

  /**
   * @param array $pfileList
   * @return FoDecisionData
   */
  public function setPfileList(array $pfileList): FoDecisionData
  {
    $this->pfileList = $pfileList;
    return $this;
  }

  /**
   * @return array
   */
  public function getUploadtreeList(): array
  {
    return $this->uploadtreeList;
  }

  /**
   * @param array $uploadtreeList
   * @return FoDecisionData
   */
  public function setUploadtreeList(array $uploadtreeList): FoDecisionData
  {
    $this->uploadtreeList = $uploadtreeList;
    return $this;
  }

  /**
   * @return array
   */
  public function getClearingDecisionList(): array
  {
    return $this->clearing_decisionList;
  }

  /**
   * @param array $clearing_decisionList
   * @return FoDecisionData
   */
  public function setClearingDecisionList(array $clearing_decisionList): FoDecisionData
  {
    $this->clearing_decisionList = $clearing_decisionList;
    return $this;
  }

  /**
   * @return array
   */
  public function getClearingEventList(): array
  {
    return $this->clearing_eventList;
  }

  /**
   * @param array $clearing_eventList
   * @return FoDecisionData
   */
  public function setClearingEventList(array $clearing_eventList): FoDecisionData
  {
    $this->clearing_eventList = $clearing_eventList;
    return $this;
  }

  /**
   * @return array
   */
  public function getClearingDecisionEventList(): array
  {
    return $this->clearing_decision_eventList;
  }

  /**
   * @param array $clearing_decision_eventList
   * @return FoDecisionData
   */
  public function setClearingDecisionEventList(array $clearing_decision_eventList): FoDecisionData
  {
    $this->clearing_decision_eventList = $clearing_decision_eventList;
    return $this;
  }

  /**
   * @return array
   */
  public function getLicenseRefBulkList(): array
  {
    return $this->license_ref_bulkList;
  }

  /**
   * @param array $license_ref_bulkList
   * @return FoDecisionData
   */
  public function setLicenseRefBulkList(array $license_ref_bulkList): FoDecisionData
  {
    $this->license_ref_bulkList = $license_ref_bulkList;
    return $this;
  }

  /**
   * @return array
   */
  public function getLicenseSetBulkList(): array
  {
    return $this->license_set_bulkList;
  }

  /**
   * @param array $license_set_bulkList
   * @return FoDecisionData
   */
  public function setLicenseSetBulkList(array $license_set_bulkList): FoDecisionData
  {
    $this->license_set_bulkList = $license_set_bulkList;
    return $this;
  }

  /**
   * @return array
   */
  public function getCopyrightList(): array
  {
    return $this->copyrightList;
  }

  /**
   * @param array $copyrightList
   * @return FoDecisionData
   */
  public function setCopyrightList(array $copyrightList): FoDecisionData
  {
    $this->copyrightList = $copyrightList;
    return $this;
  }

  /**
   * @return array
   */
  public function getCopyrightDecisionList(): array
  {
    return $this->copyright_decisionList;
  }

  /**
   * @param array $copyright_decisionList
   * @return FoDecisionData
   */
  public function setCopyrightDecisionList(array $copyright_decisionList): FoDecisionData
  {
    $this->copyright_decisionList = $copyright_decisionList;
    return $this;
  }

  /**
   * @return array
   */
  public function getCopyrightEventList(): array
  {
    return $this->copyright_eventList;
  }

  /**
   * @param array $copyright_eventList
   * @return FoDecisionData
   */
  public function setCopyrightEventList(array $copyright_eventList): FoDecisionData
  {
    $this->copyright_eventList = $copyright_eventList;
    return $this;
  }

  /**
   * @return array
   */
  public function getEccList(): array
  {
    return $this->eccList;
  }

  /**
   * @param array $eccList
   * @return FoDecisionData
   */
  public function setEccList(array $eccList): FoDecisionData
  {
    $this->eccList = $eccList;
    return $this;
  }

  /**
   * @return array
   */
  public function getEccDecisionList(): array
  {
    return $this->ecc_decisionList;
  }

  /**
   * @param array $ecc_decisionList
   * @return FoDecisionData
   */
  public function setEccDecisionList(array $ecc_decisionList): FoDecisionData
  {
    $this->ecc_decisionList = $ecc_decisionList;
    return $this;
  }

  /**
   * @return array
   */
  public function getEccEventList(): array
  {
    return $this->ecc_eventList;
  }

  /**
   * @param array $ecc_eventList
   * @return FoDecisionData
   */
  public function setEccEventList(array $ecc_eventList): FoDecisionData
  {
    $this->ecc_eventList = $ecc_eventList;
    return $this;
  }

  /**
   * @return array
   */
  public function getIpraList(): array
  {
    return $this->ipraList;
  }

  /**
   * @param array $ipraList
   * @return FoDecisionData
   */
  public function setIpraList(array $ipraList): FoDecisionData
  {
    $this->ipraList = $ipraList;
    return $this;
  }

  /**
   * @return array
   */
  public function getIpraDecisionList(): array
  {
    return $this->ipra_decisionList;
  }

  /**
   * @param array $ipra_decisionList
   * @return FoDecisionData
   */
  public function setIpraDecisionList(array $ipra_decisionList): FoDecisionData
  {
    $this->ipra_decisionList = $ipra_decisionList;
    return $this;
  }

  /**
   * @return array
   */
  public function getIpraEventList(): array
  {
    return $this->ipra_eventList;
  }

  /**
   * @param array $ipra_eventList
   * @return FoDecisionData
   */
  public function setIpraEventList(array $ipra_eventList): FoDecisionData
  {
    $this->ipra_eventList = $ipra_eventList;
    return $this;
  }

  /**
   * @return array
   */
  public function getReportInfo(): array
  {
    return $this->report_info;
  }

  /**
   * @param array $report_info
   * @return FoDecisionData
   */
  public function setReportInfo(array $report_info): FoDecisionData
  {
    $this->report_info = $report_info;
    return $this;
  }

  /**
   * @return array
   */
  public function getLicensesList(): array
  {
    return $this->licensesList;
  }

  /**
   * @param array $licensesList
   * @return FoDecisionData
   */
  public function setLicensesList(array $licensesList): FoDecisionData
  {
    $this->licensesList = $licensesList;
    return $this;
  }
}
