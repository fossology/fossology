<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\ArrayOperation;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Dao\LicenseDao;

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

  protected $alias = array(
      'mainname'=>array('mainname','Main Name'),
      'subname'=>array('subname','Sub Name'),
      'maintype'=>array('maintype','Main Type'),
      'subtype'=>array('subtype','Sub Type'),
      'compatibility'=>array('compatibility','Compatibility'),
      'text'=>array('text','Text')
      );

  /**
   * Constructor
   * @param DbManager $dbManager DB manager to use
   * @param UserDao $userDao     User Dao to use
   * @param LicenseDao $licenseDao     License Dao to use
   */
  public function __construct(DbManager $dbManager, UserDao $userDao, LicenseDao $licenseDao)
  {
    $this->dbManager = $dbManager;
    $this->userDao = $userDao;
    $this->licenseDao = $licenseDao;
  }


  /**
   * @brief Read the YAML line by line and import it.
   * @param string $filename Location of the YAML file.
   * @return string message Error message, if any. Otherwise
   *         `Read yaml: <count> licenses` on success.
   */
  public function handleFile($filename)
  {
    if (!is_file($filename) || ($handle = fopen($filename, 'r')) === false) {
      return _('Internal error');
    }
    $cnt = 0;
    $msg = '';
    $var=yaml_parse_file($filename);
    try {
      foreach ($var["rules"] as $row) {
        $log = $this-> handleYaml($row);
        if (!empty($log)) {
          $msg .= "$log\n";
        }
        $cnt++;
      }
      $msg .= _('Read yaml').(": $cnt ")._('license rules');
    } catch(\Exception $e) {
        fclose($handle);
        return $msg .= _('Error while parsing file').': '.$e->getMessage();
    }
    fclose($handle);
    return $msg;
  }

  /**
   * Handle a single row read from the YAML.
   * @param array $row   Single row from YAML
   * @return string $log Log messages
   */
  private function handleYaml($row)
  {
    if ($row["mainname"] != null) {
      $new = $this->licenseDao->getLicenseByShortName($row["mainname"], null);
      $new2= $new->getId();
      $row["mainname"]=$new2;
    }
    if ($row["subname"] != null) {
      $new = $this->licenseDao->getLicenseByShortName($row["subname"], null);
      $new2= $new->getId();
      $row["subname"]=$new2;
    }
    return $this->handleYamlLicense($row);
  }

  /**
   * @brief Update the license info in the DB.
   * @param array $row  Row with new values.
   * @param array $lfPk Matched license ID.
   * @return string Log messages.
   */
  private function updateLicense($row, $lrPk)
  {
    $stmt = __METHOD__ . '.getOldRule';
    $oldRule = $this->dbManager->getSingleRow('SELECT ' .
      'compatibility, text ' .
        'FROM license_rules WHERE lr_pk = $1', array($lrPk), $stmt);

    $stmt = __METHOD__ . '.updateLicenseRules';
    $sql = "UPDATE license_rules SET ";
    $extraParams = array();
    $param = array($lrPk);
    $old_comp= $this->dbManager->booleanFromDb($oldRule["compatibility"]);
    $new_comp= filter_var($row["compatibility"], FILTER_VALIDATE_BOOLEAN);
    if ($old_comp != $new_comp) {
      $param[] = $this->dbManager->booleanToDb($new_comp);
      $stmt .= '.compatibility';
      $extraParams[] = "compatibility=$" . count($param);
      $log .= ", updated compatibility";
    }
    if (!empty($row['text']) && $row['text'] != $oldRule['text']) {
      $param[] = $row['text'];
      $stmt .= '.text';
      $extraParams[] = "text=$" . count($param);
      $log .= ", updated text";
    }
    if (count($param) > 1) {
      $sql .= join(",", $extraParams);
      $sql .= " WHERE lr_pk=$1;";
      $this->dbManager->getSingleRow($sql, $param, $stmt);
    }
    return $log;
  }

  /**
   * @brief Handle a single row from YAML.
   *
   * The function checks if the license data is already in the DB, then
   * updates it. Otherwise inserts new row in the DB.
   * @param array $row YAML row to be inserted.
   * @return string Log messages.
   */
  private function handleYamlLicense($row)
  {
    $stmt = __METHOD__ . '.LicRules';
    $sql = "SELECT lr_pk FROM license_rules WHERE ";
    $extraParams = array();
    $param = array();
    $lr_pk="";
    if (!empty($row['mainname'])) {
      $param[] = $row['mainname'];
      $stmt .= '.lic1';
      $extraParams[] = "main_rf_fk=$" . count($param);
    }
    if (!empty($row['subname'])) {
      $param[] = $row['subname'];
      $stmt .= '.lic2';
      $extraParams[] = "sub_rf_fk=$" . count($param);
    }
    if (!empty($row['maintype'])) {
      $param[] = $row['maintype'];
      $stmt .= '.type1';
      $extraParams[] = "main_type=$" . count($param);
    }
    if (!empty($row['subtype'])) {
      $param[] = $row['subtype'];
      $stmt .= '.type2';
      $extraParams[] = "sub_type=$" . count($param);
    }
    if (count($param) == 2) {
      $sql .= join(" AND ",$extraParams);
      $res = $this->dbManager->getSingleRow($sql, $param, $stmt);
      $lr_pk= $res["lr_pk"];
    }
    if (!empty(($lr_pk))) {
      return $this->updateLicense($row, $lr_pk);
    } else {
        $return= $this->insertNewLicense($row);
    }
    return $return;
  }

  /**
   * @brief Insert a new license in DB
   * @param array $row        Rows comming from YAML
   * @return number
   */
  private function insertNewLicense($row)
  {
    $params = [
        'main_rf_fk' => $row["mainname"],
        'sub_rf_fk' => $row["subname"],
        'main_type' => $row["maintype"],
        'sub_type' => $row["subtype"],
        'text' => $row["text"],
        'compatibility' => $row["compatibility"]
    ];
    $statement = __METHOD__ . ".insertLicCompatibilityRule";
    $returning = "lr_pk";
    $returnVal = -1;
    try {
      $returnVal = $this->dbManager->insertTableRow("license_rules",
                   $params, $statement, $returning);
    }
    catch (\Exception $e) {
      $returnVal = -2;
    }
    return $returnVal;
  }
}
