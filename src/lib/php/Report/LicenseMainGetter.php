<?php
/*
 Copyright (C) 2016-2017, Siemens AG
 Author: Daniele Fognini, Shaheem Azmal M MD

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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

  public function __construct() {
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
      $allStatements[] = array(
        'licenseId' => $originLicenseId,
        'risk' => $allLicenseCols->getRisk(),
        'content' => $licenseMap->getProjectedShortname($originLicenseId),
        'text' => $allLicenseCols->getText()
      );
      
    }
    return $allStatements;
  }
  
  public function getCleared($uploadId, $groupId=null, $extended=true, $agentcall=null)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $statements = $this->getStatements($uploadId, $uploadTreeTableName, $groupId);
    if(!$extended){
      for($i=0; $i<=count($statements); $i++){
        unset($statements[$i]['risk']);
      }
    }
    return array("statements" => array_values($statements));
  }
}
