<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG
 Author: Steffen Weber
 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Test;

// setup autoloading
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/vendor/autoload.php");

use Fossology\Lib\Db\Driver\SqliteE;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use SQLite3;

class TestLiteDb extends TestAbstractDb
{
  /** @var string dbFileName */
  private $dbFileName;
  /** @var string logFileName */
  private $logFileName;

  function __construct($dbFileName = null)
  {
    if (! class_exists('Sqlite3')) {
      throw new \Exception("Class SQLite3 not found");
    }

    date_default_timezone_set("UTC");
    if (! isset($dbFileName)) {
      $dbFileName = ":memory:";
    } else {
      if (file_exists($dbFileName)) {
        unlink($dbFileName);
      }
    }
    $this->dbFileName = $dbFileName;

    require (dirname(dirname(__FILE__)).'/common-container.php');

    global $container;
    $logger = $container->get('logger');
    $this->logFileName = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/db.sqlite.log';
    $logger->pushHandler(new StreamHandler($this->logFileName, Logger::DEBUG));

    $sqlite3Connection = new SQLite3($this->dbFileName, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);

    $liteDb = new SqliteE($sqlite3Connection);
    $container->get('db.manager')->setDriver($liteDb);
    $this->dbManager = $container->get('db.manager');
  }

  function __destruct()
  {
    if (file_exists($this->logFileName)) {
      unlink($this->logFileName);
    }
    $this->dbManager = null;
  }

  /**
   * @param array $tableList
   * @param bool $invert
   */
  public function createPlainTables($tableList, $invert=FALSE)
  {
    $coreSchemaFile = $this->dirnameRec(__FILE__, 4) . '/www/ui/core-schema.dat';
    $Schema = array();
    require($coreSchemaFile);
    foreach ($Schema['TABLE'] as $tableName=>$tableCols) {
      if ($invert^!in_array($tableName, $tableList) || array_key_exists($tableName, $Schema['INHERITS'])) {
        continue;
      }
      $columns = array();
        // $pattern = "ALTER TABLE \"license_ref\" ADD COLUMN \"rf_pk\" int8;"";
      foreach ($tableCols as $attributes) {
        $sql = preg_replace('/ DEFAULT .*/', '', $attributes["ADD"]);
        $alterSql = explode('"', $sql);
        $columns[$alterSql[3]] = "$alterSql[3] " . $alterSql[4];
      }
      $createSql = "CREATE TABLE $tableName (" . implode(',', $columns) . ')';
      $this->dbManager->queryOnce($createSql);
    }
  }

  /**
   * @brief convert sql string to something the drive understands
   * @param string $sql
   * @return string
   */
  protected function queryConverter($sql)
  {
    $sql = str_replace(' false,', " 'false',", $sql);
    $sql = str_replace(' true,', " 'true',", $sql);
    $sql = str_replace(' false)', " 'false')", $sql);
    $sql = str_replace(' true)', " 'true')", $sql);
    return $sql;
  }
}
