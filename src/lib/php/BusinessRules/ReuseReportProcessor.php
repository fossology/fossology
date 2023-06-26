<?php
/*
 SPDX-FileCopyrightText: © 2022 Rohit Pandey <rohit.pandey4900@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\BusinessRules\DetectLicensesFolder;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Data\LicenseRef;

/**
 * @class ReuseReportProcessor
 * @brief Process Reuse Report
 */
class ReuseReportProcessor
{
  /** @var DbManager */
  private $dbManager;
  /** @var UploadDao */
  private $uploadDao;
  /** @var DetectLicensesFolder */
  private $detectLicensesFolder;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var LicenseMap */
  private $licenseProjector;

  function __construct()
  {
    $this->dbManager = $GLOBALS['container']->get('db.manager');
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
    $this->clearingDao = $GLOBALS['container']->get('dao.clearing');
    $this->detectLicensesFolder = $GLOBALS['container']->get('businessrules.detectlicensesfolder');
  }

  /**
   * @brief Get status of license decleared in LICENSES folder
   * @param int $uploadId
   * @return array $vars - array of declearedLicense, clearedLicense, usedLicense,
   *               unusedLicense and missingLicense
   */
  public function getReuseSummary($uploadId)
  {
    $declearedLicenses = $this->detectLicensesFolder->getDeclearedLicenses($uploadId);
    $groupId = Auth::getGroupId();
    $uploadtreeTablename = GetUploadtreeTableName($uploadId);
    $uploadTreeId = $this->uploadDao->getUploadParent($uploadId);
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($uploadTreeId, $uploadtreeTablename);
    $clearedLicenses = $this->clearingDao->getClearedLicenses($itemTreeBounds, $groupId);
    $this->licenseProjector = new LicenseMap($this->dbManager,$groupId,LicenseMap::CONCLUSION,true);
    $concludedLicenses = [];
    /** @var LicenseRef $licenseRef */
    if (!empty($clearedLicenses)) {
      foreach ($clearedLicenses as $licenseRef) {
        $projectedName = $this->licenseProjector->getProjectedShortname($licenseRef->getId(),$licenseRef->getShortName());
        $concludedLicenses[] = $projectedName;
      }
    }

    $vars = [];
    $vars["declearedLicense"] = implode(", ", $declearedLicenses);
    $vars["clearedLicense"] = implode(", ", $concludedLicenses);

    if (empty($declearedLicenses)) {
      $vars["usedLicense"] = "";
      $vars["unusedLicense"] = "";
      $vars["missingLicense"] = implode(", ", $concludedLicenses);
      return $vars;
    }

    $vars["usedLicense"] = implode(", ", array_intersect($declearedLicenses, $concludedLicenses));
    $vars["unusedLicense"] = implode(", ", array_diff($declearedLicenses, $concludedLicenses));
    $vars["missingLicense"] = implode(", ", array_diff($concludedLicenses, $declearedLicenses));
    return $vars;
  }
}
