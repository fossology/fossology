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
  /** @var Boolean */
  private $onlyExpressions = false;

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

  protected function getStatements($uploadId, $uploadTreeTableName, $groupId = null, $includeExpressions = false)
  {
    $dbManager = $GLOBALS['container']->get('db.manager');
    $licenseMap = new LicenseMap($dbManager, $groupId, LicenseMap::REPORT, true);
    $mainLicIds = $this->clearingDao->getMainLicenseIds($uploadId, $groupId);

    $allStatements = array();
    foreach ($mainLicIds as $originLicenseId) {
      $allLicenseCols = $this->licenseDao->getLicenseById($originLicenseId, $groupId);
      if ($allLicenseCols->getSpdxId() == 'LicenseRef-fossology-License-Expression') {
        if ($includeExpressions) {
          $expression = $allLicenseCols->getExpression($this->licenseDao, $groupId);
          $allStatements[] = array(
            'licenseId' => $originLicenseId,
            'risk' => $allLicenseCols->getRisk(),
            'content' => $expression,
            'text' => 'License Expression',
            'name' => $expression
          );
        }
        continue;
      }
      if (!$this->onlyExpressions) {
        $allStatements[] = array(
          'licenseId' => $originLicenseId,
          'risk' => $allLicenseCols->getRisk(),
          'content' => $licenseMap->getProjectedSpdxId($originLicenseId),
          'text' => $allLicenseCols->getText(),
          'name' => $licenseMap->getProjectedShortname($originLicenseId,
              $allLicenseCols->getShortName())
        );
      }
    }
    return $allStatements;
  }

  public function getCleared($uploadId, $objectAgent, $groupId=null, $extended=true, $agentcall=null, $isUnifiedReport=false, $includeExpressions=false)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $statements = $this->getStatements($uploadId, $uploadTreeTableName, $groupId, $includeExpressions);
    if (!$extended) {
      for ($i=0; $i<=count($statements); $i++) {
        unset($statements[$i]['risk']);
        unset($statements[$i]['licenseId']);
      }
    }
    return array("statements" => array_values($statements));
  }

  /**
   * @param boolean $displayOnlyAcknowledgements
   */
  public function setOnlyExpressions($displayOnlyExpressions)
  {
    $this->onlyExpressions = $displayOnlyExpressions;
  }
}
