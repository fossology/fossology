<?php
/*
 SPDX-FileCopyrightText: © 2017, 2020 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\LicenseRef;

/**
 * @class ObligationsToLicenses
 * @brief Handles license obligations
 */
class ObligationsGetter
{
  /** @var LicenseDao $licenseDao
   * LicenseDao object
   */
  private $licenseDao;

  /** @var ClearingDao $clearingDao
   * ClearingDao object
   */
  private $clearingDao;

  /** @var UploadDao $uploadDao
   * UploadDao object
   */
  private $uploadDao;

  public function __construct()
  {
    global $container;
    $this->licenseDao = $container->get('dao.license');
    $this->clearingDao = $container->get('dao.clearing');
    $this->uploadDao = $container->get('dao.upload');
  }

  /**
   * @brief For given list of license statements,
   * return obligations and white lists
   * @param array $licenseStatements
   * @param array $mainLicenseStatements
   * @param int $uploadId
   * @param int $groupId
   * @return array [obligations, whitelist]
   */
  function getObligations($licenseStatements, $mainLicenseStatements, $uploadId, $groupId)
  {
    $whiteLists = array();
    $licenseIds = $this->contentOnly($licenseStatements) ?: array();
    $mainLicenseIds = $this->contentOnly($mainLicenseStatements);

    if (!empty($mainLicenseIds)) {
      $allLicenseIds = array_unique(array_merge($licenseIds, $mainLicenseIds));
    } else {
      $allLicenseIds = array_unique($licenseIds);
    }

    $bulkAddIds = $this->getBulkAddLicenseList($uploadId, $groupId);
    if (!empty($bulkAddIds)) {
      $allLicenseIds = array_unique(array_merge($licenseIds, $bulkAddIds));
    }

    $obligationRef = $this->licenseDao->getLicenseObligations($allLicenseIds) ?: array();
    $obligationCandidate = $this->licenseDao->getLicenseObligations($allLicenseIds, true) ?: array();
    $obligations = array_merge($obligationRef, $obligationCandidate);
    $onlyLicenseIdsWithObligation = array_column($obligations, 'rf_fk');
    $licenseWithObligations = array_unique(array_intersect($onlyLicenseIdsWithObligation, $allLicenseIds));
    $licenseWithoutObligations = array_diff($allLicenseIds, $licenseWithObligations) ?: array();
    foreach ($licenseWithoutObligations as $licenseWithoutObligation) {
      $license = $this->licenseDao->getLicenseById($licenseWithoutObligation);
      if (!empty($license)) {
        $whiteLists[] = $license->getSpdxId();
      }
    }
    $newobligations = $this->groupObligations($obligations, $uploadId);
    return array($newobligations, $whiteLists);
  }

  /**
   * @brief Get list of licenses added by Monk bulk
   * @param int $uploadId
   * @param int $groupId
   * @return array List of license ids
   */
  function getBulkAddLicenseList($uploadId, $groupId)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $parentTreeBounds = $this->uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);
    $bulkHistory = $this->clearingDao->getBulkHistory($parentTreeBounds, $groupId, false);
    $licenseId = [];
    if (!empty($bulkHistory)) {
      foreach ($bulkHistory as $key => $value) {
        if (empty($value['id'])) {
          unset($bulkHistory[$key]);
        }
      }
      $licenseLists = array_column($bulkHistory, 'addedLicenses');
      $allLicenses = array();
      foreach ($licenseLists as $licenseList) {
        $allLicenses = array_unique(array_merge($allLicenses, $licenseList));
      }
      foreach ($allLicenses as $allLicense) {
        $license = $this->licenseDao->getLicenseByShortName($allLicense);
        if (!empty($license)) {
          $licenseId[] = $license->getId();
        }
      }
    }
    return $licenseId;
  }

  /**
   * @brief Group obligations based on $groupBy
   * @param array $obligations
   * @return array
   */
  function groupObligations($obligations, $uploadId)
  {
    $groupedOb = array();
    $row = $this->uploadDao->getReportInfo($uploadId);
    $excludedObligations = (array) json_decode($row['ri_excluded_obligations'], true);
    foreach ($obligations as $obligation ) {
      $obTopic = $obligation['ob_topic'];
      $obText = $obligation['ob_text'];
      $licenseName = LicenseRef::convertToSpdxId($obligation['rf_shortname'],
        $obligation['rf_spdx_id']);
      $groupBy = $obText;
      if (!empty($excludedObligations) && array_key_exists($obTopic, $excludedObligations)) {
        $obligationLicenseNames = $excludedObligations[$obTopic];
      } else {
        $obligationLicenseNames = array();
      }
      if (!in_array($licenseName, $obligationLicenseNames)) {
        if (array_key_exists($groupBy, $groupedOb)) {
          $currentLics = &$groupedOb[$groupBy]['license'];
          if (!in_array($licenseName, $currentLics)) {
            $currentLics[] = $licenseName;
          }
        } else {
          $singleOb = array(
            "topic" => $obTopic,
            "text" => $obText,
            "license" => array($licenseName)
          );
          $groupedOb[$groupBy] = $singleOb;
        }
      }
    }
    return $groupedOb;
  }

  /**
   * @brief From a list of license statements, return only license id
   * @param array $licenseStatements
   * @return array List of license ids
   */
  function contentOnly($licenseStatements)
  {
    $licenseId = [];
    foreach ($licenseStatements as $licenseStatement) {
      $licenseId[] = $licenseStatement["licenseId"];
    }
    return $licenseId;
  }
}
