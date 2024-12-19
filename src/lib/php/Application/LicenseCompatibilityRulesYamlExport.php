<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\Dao\CompatibilityDao;
use Fossology\Lib\Db\DbManager;

/**
 * @file
 * @brief Helper class to export license list as a YAML from the DB
 */

/**
 * @class LicenseCompatibilityRulesYamlExport
 * @brief Helper class to export license list as a YAML from the DB
 */
class LicenseCompatibilityRulesYamlExport
{
  /** @var DbManager $dbManager
   * DB manager in use */
  private $dbManager;
  /** @var CompatibilityDao $compatibilityDao
   * Compatibility Dao object */
  private $compatibilityDao;
  /**
   * Constructor
   * @param DbManager $dbManager DB manager to use.
   * @param CompatibilityDao $compatibilityDao Compatibility DAO to use
   */
  public function __construct(DbManager $dbManager,
                              CompatibilityDao $compatibilityDao)
  {
    $this->dbManager = $dbManager;
    $this->compatibilityDao = $compatibilityDao;
  }
  /**
   * @brief Create the YAML from the DB
   * @param int $lr Set the license ID to get only one license, set 0 to get all
   * @return string yaml
   */
  public function createYaml($lr=0)
  {
    $sql = "SELECT lrm.rf_shortname AS firstname, lrs.rf_shortname AS secondname,
       first_type AS firsttype, second_type AS secondtype,
       CASE compatibility WHEN 't' THEN 'true' ELSE 'false' END AS compatibility,
       comment
      FROM license_rules r
        LEFT JOIN license_ref lrm ON lrm.rf_pk = r.first_rf_fk
        LEFT JOIN license_ref lrs ON lrs.rf_pk = r.second_rf_fk";
    $param = [];
    if ($lr > 0) {
      $stmt = __METHOD__ . '.lr';
      $param[] = $lr;
      $sql .= ' WHERE lr_pk = $'.count($param);
      $row = $this->dbManager->getSingleRow($sql, $param, $stmt);
      $vars = $row ?: [];
    } else {
      $stmt = __METHOD__;
      $sql .= ' ORDER BY lr_pk';
      $vars = $this->dbManager->getRows($sql, $param, $stmt);
    }
    $rules = [];
    $rules["default"] = $this->compatibilityDao->getDefaultCompatibility();
    $rules["rules"] = $vars;
    return yaml_emit($rules);
  }
}
