<?php
/*
 SPDX-FileCopyrightText: © 2016-2017 Siemens AG
 Author: Daniele Fognini, Shaheem Azmal M MD

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\LicenseRef;

class LicenseMainGetter extends ClearedGetterCommon
{
  /** @var ClearingDao */
  private $clearingDao;

  /** @var LicenseDao */
  private $licenseDao;

  public function __construct()
  {
    global $container;

    $this->clearingDao = $container->get('dao.clearing');
    $this->licenseDao = $container->get('dao.license');

    parent::__construct($groupBy = 'text');
  }

  protected function getStatements($uploadId, $uploadTreeTableName, $groupId = null)
  {
    $dbManager = $GLOBALS['container']->get('db.manager');
    $licenseMap = new LicenseMap($dbManager, $groupId, LicenseMap::REPORT, true);
    $mainLicIds = $this->clearingDao->getMainLicenseIds($uploadId, $groupId);
    $customTexts = $this->clearingDao->getMainLicenseReportInfos($uploadId, $groupId);
    $customTextsByProjected = array();
    foreach ($customTexts as $rawId => $customText) {
      $projectedId = $licenseMap->getProjectedId($rawId);
      if (!array_key_exists($projectedId, $customTextsByProjected)) {
        $customTextsByProjected[$projectedId] = $customText;
      }
    }

    $allStatements = array();
    foreach ($mainLicIds as $originLicenseId) {
      $projectedId = $licenseMap->getProjectedId($originLicenseId);
      $baseLicense = $this->licenseDao->getLicenseById($projectedId, $groupId);
      if ($baseLicense === null) {
        error_log("Error: License ID " . $projectedId . " not found in the database.");
        continue;
      }
      $customText = null;
      if (array_key_exists($originLicenseId, $customTexts)) {
        $customText = $customTexts[$originLicenseId];
      } elseif (array_key_exists($projectedId, $customTextsByProjected)) {
        $customText = $customTextsByProjected[$projectedId];
      }
      if ($customText !== null && $customText !== '') {
        $customShortName = $baseLicense->getShortName() . '-' . md5($customText);
        $customShortName = LicenseRef::convertToSpdxId($customShortName, '');
        $allStatements[] = array(
          'licenseId' => $projectedId,
          'risk' => $baseLicense->getRisk(),
          'content' => $customShortName,
          'text' => $customText,
          'name' => $customShortName
        );
        continue;
      }
      $allStatements[] = array(
        'licenseId' => $projectedId,
        'risk' => $baseLicense->getRisk(),
        'content' => $licenseMap->getProjectedSpdxId($originLicenseId),
        'text' => $baseLicense->getText(),
        'name' => $licenseMap->getProjectedShortname($originLicenseId, $baseLicense->getShortName())
      );
    }
    return $allStatements;
  }

  public function getCleared($uploadId, $objectAgent, $groupId=null, $extended=true, $agentcall=null, $isUnifiedReport=false)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $statements = $this->getStatements($uploadId, $uploadTreeTableName, $groupId);
    if (!$extended) {
      for ($i=0; $i<=count($statements); $i++) {
        unset($statements[$i]['risk']);
        unset($statements[$i]['licenseId']);
      }
    }
    return array("statements" => array_values($statements));
  }
}
