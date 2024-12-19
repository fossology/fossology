<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\Dao\CompatibilityDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\ArrayOperation;

/**
 * @file
 * @brief Import licenses from YAML
 */

/**
 * @class LicenseCompatibilityRulesYamlImport
 * @brief Import licenses from YAML
 */
class LicenseCompatibilityRulesYamlImport
{
  /** @var DbManager $dbManager
   * DB manager to use */
  protected $dbManager;
  /** @var UserDao $userDao
   * User DAO to use */
  protected $userDao;
  /** @var LicenseDao $licenseDao
   * License DAO to use */
  protected $licenseDao;
  /** @var CompatibilityDao $compatibilityDao
   * Compatibility DAO to use */
  protected $compatibilityDao;

  protected $alias = [
    'firstname'     => ['firstname', 'mainname', 'First Name'],
    'secondname'    => ['secondname', 'subname', 'Second Name'],
    'firsttype'     => ['firsttype', 'maintype', 'First Type'],
    'secondtype'    => ['secondtype', 'subtype', 'Second Type'],
    'compatibility' => ['compatibility', 'Compatibility'],
    'comment'       => ['comment', 'text', 'Comment']
  ];

  /**
   * Constructor
   * @param DbManager $dbManager   DB manager to use
   * @param UserDao $userDao       User Dao to use
   * @param LicenseDao $licenseDao License Dao to use
   * @param CompatibilityDao $compatibilityDao Compatibility DAO to use
   */
  public function __construct(DbManager $dbManager, UserDao $userDao,
                              LicenseDao $licenseDao,
                              CompatibilityDao $compatibilityDao)
  {
    $this->dbManager = $dbManager;
    $this->userDao = $userDao;
    $this->licenseDao = $licenseDao;
    $this->compatibilityDao = $compatibilityDao;
  }

  /**
   * @brief Read the YAML line by line and import it.
   * @param string $filename Location of the YAML file.
   * @return string Error message, if any. Otherwise
   *         `Read yaml: <count> licenses` on success.
   */
  public function handleFile($filename)
  {
    if (!is_file($filename) || ($handle = fopen($filename, 'r')) === false) {
      return _('Internal error');
    }
    $cnt = 0;
    $msg = '';
    $file_content = yaml_parse_file($filename);
    try {
      foreach ($file_content["rules"] as $row) {
        $log = $this->handleYaml($row);
        if (!empty($log)) {
          $msg .= "$log\n";
        }
        $cnt++;
      }
      $msg .= _('Read yaml').(": $cnt ")._('license rules');
    } catch(\Exception $e) {
      fclose($handle);
      return $msg . _('Error while parsing file') . ': ' . $e->getMessage();
    }
    fclose($handle);
    return $msg;
  }

  /**
   * Handle a single row read from the YAML.
   * @param array $row Single row from YAML
   * @return string Log messages
   */
  private function handleYaml($row)
  {
    $normalizedRow = [];
    foreach (array_keys($this->alias) as $key) {
      $col = ArrayOperation::multiSearch($this->alias[$key], array_keys($row));
      if ($col === false) {
        throw new \UnexpectedValueException("Unable to find key for $key in " .
          "YAML");
      }
      $col = array_keys($row)[$col];
      $normalizedRow[$key] = $row[$col];
    }
    if ($normalizedRow["firstname"] != null) {
      $firstLicense = $this->licenseDao->getLicenseByShortName(
          $normalizedRow["firstname"], null);
      $firstLicenseId = $firstLicense->getId();
      $normalizedRow["firstname"] = $firstLicenseId;
    }
    if ($normalizedRow["secondname"] != null) {
      $secondLicense = $this->licenseDao->getLicenseByShortName(
          $normalizedRow["secondname"], null);
      $secondLicenseId = $secondLicense->getId();
      $normalizedRow["secondname"] = $secondLicenseId;
    }
    return $this->handleYamlLicense($normalizedRow);
  }

  /**
   * @brief Update the license info in the DB.
   * @param array $row Row with new values.
   * @param int $lrPk  Matched license ID.
   * @return string Log messages.
   */
  private function updateLicense($row, $lrPk)
  {
    $rule = [];
    $oldRule = $this->dbManager->getSingleRow("SELECT
        compatibility, comment FROM license_rules WHERE lr_pk = $1",
        [$lrPk], __METHOD__ . '.getOldRule');

    $old_comp= $this->dbManager->booleanFromDb($oldRule["compatibility"]);
    $new_comp= filter_var($row["compatibility"], FILTER_VALIDATE_BOOLEAN);

    $log = [];
    if ($old_comp != $new_comp) {
      $rule["compatibility"] = $this->dbManager->booleanToDb($new_comp);
      $log[] = "updated compatibility";
    }
    if (!empty($row['comment']) && $row['comment'] != $oldRule['comment']) {
      $rule["comment"] = $row["comment"];
      $log[] = "updated comment";
    }
    if (count($rule) > 1) {
      try {
        $this->compatibilityDao->updateRuleFromArray([$lrPk => $rule]);
      } catch (\UnexpectedValueException $e) {
        $log[] = $e->getMessage();
      }
    }
    return join(", ", $log);
  }

  /**
   * @brief Handle a single row from YAML.
   *
   * The function checks if the license data is already in the DB, then
   * updates it. Otherwise, inserts new row in the DB.
   * @param array $row YAML row to be inserted.
   * @return string Log messages.
   */
  private function handleYamlLicense($row)
  {
    $stmt = __METHOD__ . '.LicRules';
    $sql = "SELECT lr_pk FROM license_rules WHERE ";
    $extraParams = [];
    $param = [];
    $lr_pk = "";
    if (!empty($row['firstname'])) {
      $param[] = $row['firstname'];
      $stmt .= '.lic1';
      $extraParams[] = "first_rf_fk=$" . count($param);
    }
    if (!empty($row['secondname'])) {
      $param[] = $row['secondname'];
      $stmt .= '.lic2';
      $extraParams[] = "second_rf_fk=$" . count($param);
    }
    if (!empty($row['firsttype'])) {
      $param[] = $row['firsttype'];
      $stmt .= '.type1';
      $extraParams[] = "first_type=$" . count($param);
    }
    if (!empty($row['secondtype'])) {
      $param[] = $row['secondtype'];
      $stmt .= '.type2';
      $extraParams[] = "second_type=$" . count($param);
    }
    if (count($param) == 2) {
      $sql .= join(" AND ", $extraParams);
      $res = $this->dbManager->getSingleRow($sql, $param, $stmt);
      if ($res) {
        $lr_pk = $res["lr_pk"];
      }
    }
    if (!empty($lr_pk)) {
      return $this->updateLicense($row, $lr_pk);
    } else {
      $return = $this->insertNewLicense($row);
    }
    return $return;
  }

  /**
   * @brief Insert a new license in DB
   * @param array $row Rows coming from YAML
   * @return int
   */
  private function insertNewLicense($row)
  {
    return $this->compatibilityDao->insertRule($row["firstname"],
        $row["secondname"], $row["firsttype"], $row["secondtype"],
        $row["comment"], $row["compatibility"]);
  }
}
