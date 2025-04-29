<?php
/*
 SPDX-FileCopyrightText: © 2023 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use Fossology\Lib\Db\DbManager;

/**
 * Utility class for license and obligation information
 */
class LicenseObligationUtility
{
  /** @var DbManager */
  private $dbManager;

  /**
   * Constructor
   * @param DbManager $dbManager Database manager
   */
  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
  }

  /**
   * @brief Get license and obligation counts and last updated info
   * @return array Array with license and obligation information
   */
  public function getLicenseAndObligationInfo()
  {
    $licenseInfo = $obligationInfo = null;

    try {
      // Get license count
      $sql = "SELECT count(*) AS license_count FROM license_ref WHERE rf_active = 't'";
      $result = $this->dbManager->getSingleRow($sql, array(), __METHOD__ . '.licenseCount');
      if ($result) {
        $licenseInfo = array(
          'count' => $result['license_count'],
          'lastUpdated' => $this->getLastUpdateTime('license_ref')
        );
      }

      // Get obligation count
      $sql = "SELECT count(*) AS obligation_count FROM obligation_ref WHERE ob_active = 't'";
      $result = $this->dbManager->getSingleRow($sql, array(), __METHOD__ . '.obligationCount');
      if ($result) {
        $obligationInfo = array(
          'count' => $result['obligation_count'],
          'lastUpdated' => $this->getLastUpdateTime('obligation_ref')
        );
      }
    } catch (\Exception $e) {
      
    }

    return array($licenseInfo, $obligationInfo);
  }

  /**
   * @brief Get the last update time for a table
   * @param string $tableName Table name to check
   * @return string Formatted date string
   */
  public function getLastUpdateTime($tableName)
  {
    try {
      $result = null;
      if ($tableName === 'license_ref') {
        $sql = "SELECT rf_ts FROM license_ref ORDER BY rf_ts DESC LIMIT 1";
        $result = $this->dbManager->getSingleRow($sql, array(), __METHOD__ . '.licenseLastUpdate');
        if ($result) {
          return date('Y-m-d', strtotime($result['rf_ts']));
        }
      } elseif ($tableName === 'obligation_ref') {
        $sql = "SELECT ob_pk, ob_modificator, ob_modified FROM obligation_ref ORDER BY ob_modified DESC LIMIT 1";
        $result = $this->dbManager->getSingleRow($sql, array(), __METHOD__ . '.obligationLastUpdate');
        if ($result) {
          return date('Y-m-d', strtotime($result['ob_modified']));
        }
      }
    } catch (\Exception $e) {

    }
    
    return date('Y-m-d'); // Default to current date if no update time found
  }
} 