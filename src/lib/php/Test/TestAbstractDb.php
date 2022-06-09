<?php
/*
 SPDX-FileCopyrightText: © 2015, 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Test;

// setup autoloading
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/vendor/autoload.php");

use Fossology\Lib\Db\DbManager;

abstract class TestAbstractDb
{
  /** @var DbManager */
  protected $dbManager;

  protected function dirnameRec($path, $depth = 1)
  {
    for ($i = 0; $i < $depth; $i ++) {
      $path = dirname($path);
    }
    return $path;
  }

  /**
   * @param array $tableList
   * @param bool $invert
   */
  abstract function createPlainTables($tableList, $invert=false);

  /**
   * @param array $tableList
   * @param bool $invert
   */
  public function insertData($tableList, $invert=FALSE, $dataFile=null)
  {
    $testdataFile = $dataFile ?: dirname(__FILE__) . '/testdata.sql';
    $testdata = file_get_contents($testdataFile);
    $delimiter = 'INSERT INTO ';
    $offset = strpos($testdata, $delimiter);
    while (false !== $offset) {
      $nextOffset = strpos($testdata, $delimiter, $offset + 1);
      if (false === $nextOffset) {
        $sql = substr($testdata, $offset);
      } else {
        $sql = substr($testdata, $offset, $nextOffset - $offset);
      }
      $table = array();
      preg_match('/^INSERT INTO (?P<name>\w+) /', $sql, $table);
      if (($invert ^ ! in_array($table['name'], $tableList))) {
        $offset = $nextOffset;
        continue;
      }
      $this->dbManager->queryOnce($this->queryConverter($sql));
      $offset = $nextOffset;
    }
  }

  /**
   * @brief convert sql string to something the drive understands
   * @param string $sql
   * @return string
   */
  protected function queryConverter($sql)
  {
    return $sql;
  }

  public function insertData_license_ref($limit=140)
  {
    $keysToBeChanged = array(
      'rf_OSIapproved' => '"rf_OSIapproved"',
      'rf_FSFfree'=> '"rf_FSFfree"',
      'rf_GPLv2compatible' => '"rf_GPLv2compatible"',
      'rf_GPLv3compatible'=> '"rf_GPLv3compatible"',
      'rf_Fedora' => '"rf_Fedora"'
      );

    /** import licenseRef.json */
    $LIBEXECDIR = $this->dirnameRec(__FILE__, 5) . '/install/db';
    $jsonData = json_decode(file_get_contents("$LIBEXECDIR/licenseRef.json"), true);
    $statementName = __METHOD__.'.insertInToLicenseRef';
    foreach ($jsonData as $licenseArrayKey => $licenseArray) {
      $arrayKeys = array_keys($licenseArray);
      $arrayValues = array_values($licenseArray);
      $keys = strtr(implode(",", $arrayKeys), $keysToBeChanged);
      $valuePlaceHolders = "$" . join(",$",range(1, count($arrayKeys)));
      $md5PlaceHolder = "$". (count($arrayKeys) + 1);
      $arrayValues[] = $licenseArray['rf_text'];
      $SQL = "INSERT INTO license_ref ( $keys,rf_md5 ) " .
      "VALUES ($valuePlaceHolders,md5($md5PlaceHolder));";
      $this->dbManager->prepare($statementName, $SQL);
      $this->dbManager->execute($statementName, $arrayValues);
      if ($licenseArrayKey >= $limit) {
        break;
      }
    }
  }

  /**
   * @param string $type
   * @param array $elementList
   * @param bool $invert
   */
  protected function applySchema($type, $elementList, $invert=false)
  {
    $coreSchemaFile = $this->dirnameRec(__FILE__, 4) . '/www/ui/core-schema.dat';
    $Schema = array();
    require($coreSchemaFile);
    foreach ($Schema[$type] as $viewName => $sql) {
      if ($invert ^ ! in_array($viewName, $elementList)) {
        continue;
      }
      $sqlCreate = is_array($sql) ? $sql['CREATE'] : $sql;
      $this->dbManager->queryOnce($sqlCreate);
    }
  }

  /**
   * @param array $viewList
   * @param bool $invert
   */
  public function createViews($viewList, $invert=FALSE)
  {
    $this->applySchema('VIEW', $viewList, $invert);
  }

  /**
   * @param array $tableList
   * @param bool $invert
   */
  public function alterTables($tableList, $invert=FALSE)
  {
    $coreSchemaFile = $this->dirnameRec(__FILE__, 4) . '/www/ui/core-schema.dat';
    $Schema = array();
    require($coreSchemaFile);
    $attributeKey = "ALTER";
    foreach ($Schema['TABLE'] as $tableName => $tableCols) {
      if ($invert ^ ! in_array($tableName, $tableList)) {
        continue;
      }
      foreach ($tableCols as $attributes) {
        if (array_key_exists($attributeKey, $attributes)) {
          $this->dbManager->queryOnce($attributes[$attributeKey]);
        }
      }
    }
  }

  public function &getDbManager()
  {
    return $this->dbManager;
  }
}
