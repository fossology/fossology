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

    $allStatements = array();
    foreach ($mainLicIds as $originLicenseId) {
      $allLicenseCols = $this->licenseDao->getLicenseById($originLicenseId, $groupId);
      // Null-check: if the license is missing, log and skip this ID.
      if ($allLicenseCols === null) {
        error_log("Error: License ID " . $originLicenseId . " not found in the database.");
        continue; // Skip this license and continue with others
      }
      $allStatements[] = array(
        'licenseId' => $originLicenseId,
        'risk' => $allLicenseCols->getRisk(),
        'content' => $licenseMap->getProjectedSpdxId($originLicenseId),
        'text' => $allLicenseCols->getText(),
        'name' => $licenseMap->getProjectedShortname($originLicenseId, $allLicenseCols->getShortName())
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
