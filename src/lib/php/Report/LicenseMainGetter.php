<?php
/*
 Copyright (C) 2014, Siemens AG
 Author: Daniele Fognini

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
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\License;
use Fossology\Lib\Db\DbManager;

class LicenseMainGetter extends ClearedGetterCommon
{
  /** @var ClearingDao */
  private $clearingDao;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var DbManager */
  private $dbManager;
  /** @var string[] */
  private $licenseCache = array();

  public function __construct() {
    global $container;

    $this->clearingDao = $container->get('dao.clearing');
    $this->licenseDao = $container->get('dao.license');
    $this->dbManager = $container->get('db.manager');

    parent::__construct($groupBy = 'text');
  }

  protected function getStatements($uploadId, $uploadTreeTableName, $groupId = null)
  {
    $licenseMap = new LicenseMap($this->dbManager, $groupId, LicenseMap::REPORT, true);
    $mainLicIds = $this->clearingDao->getMainLicenseIds($uploadId, $groupId);

    $allStatements = array();
    foreach ($mainLicIds as $originLicenseId) {
      $allStatements[] = array(
        'content' => $licenseMap->getProjectedShortname($originLicenseId),
        'text' => ''
      );
      
    }

    return $allStatements;
  }
  
  public function getCleared($uploadId, $groupId=null)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $statements = $this->getStatements($uploadId, $uploadTreeTableName, $groupId);
    return array("statements" => array_values($statements));
  }
}
