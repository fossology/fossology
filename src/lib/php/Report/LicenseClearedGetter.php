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

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\License;

class LicenseClearedGetter extends ClearedGetterCommon
{
  /** @var ClearingDao */
  private $clearingDao;

  /** @var LicenseDao */
  private $licenseDao;

  private $licenseCache = array();

  public function __construct() {
    global $container;

    $this->clearingDao = $container->get('dao.clearing');
    $this->licenseDao = $container->get('dao.license');

    parent::__construct();
  }

  protected function getStatements($uploadId, $uploadTreeTableName, $groupId = null)
  {
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId,$uploadTreeTableName);
    $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $groupId);

    $ungroupedStatements = array();
    foreach ($clearingDecisions as $clearingDecision) {
      /** @var ClearingDecision $clearingDecision */
      foreach ($clearingDecision->getPositiveLicenses() as $clearingLicense) {
        $clid = $clearingLicense->getId();
        if(empty($clid))
        {
          $msg = "The CdId is ".$clearingDecision->getClearingId();
          trigger_error($msg);
        }

        $ungroupedStatements[] = array(
          'content' => $clearingLicense->getShortName(),
          'uploadtree_pk' => $clearingDecision->getUploadTreeId(),
          'description' => $this->getCachedLicenseText($clearingLicense->getId()),
          'textfinding' => $clearingLicense->getShortName()
        );
      }
    }

    return $ungroupedStatements;
  }

  /**
   * @param int $licenseId
   * @return License
   */
  protected function getCachedLicenseText($licenseId)
  {
    if (!array_key_exists($licenseId, $this->licenseCache)) {
      $this->licenseCache[$licenseId] = $this->licenseDao->getLicenseById($licenseId);
    }
    return $this->licenseCache[$licenseId]->getText();
  }
}
