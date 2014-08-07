<?php
/*
Copyright (C) 2014, Siemens AG
Author: Steffen Weber

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

namespace Fossology\Lib\Test;

// setup autoloading
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/vendor/autoload.php");

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Db\Driver\SqliteE;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use SQLite3;

class TestLiteDb
{
  /** * @var dbManager */
  private $dbManager;
  /** @var string dbFileName */
  private $dbFileName;
  /** @var string logFileName */
  private $logFileName;

  function __construct()
  {
    if (!class_exists('Sqlite3'))
    {
      throw new \Exception("Class SQLite3 not found");
    }

    date_default_timezone_set("UTC");
    $this->dbFileName = ":memory:"; // "fosstest_" . date("Ymdd") . "_" . rand() . '.db';
    
    require (dirname(dirname(__FILE__)).'/common-container.php');

    $logger = new Logger('default');
    $this->logFileName = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . 'db.sqlite.log';
    $logger->pushHandler(new StreamHandler($this->logFileName, Logger::DEBUG));

    $dbManager = new DbManager($logger);
    $sqlite3Connection = new SQLite3($this->dbFileName, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);

    $dbManager->setDriver(new SqliteE($sqlite3Connection));
    $this->dbManager = &$dbManager;
  }
  
  function __destruct()
  {
    if (file_exists($this->logFileName))
    {
      unlink($this->logFileName);
    }
    $this->dbManager = null;    
  }
  
   private function dirnameRec($path, $depth = 1)
  {
    for ($i = 0; $i < $depth; $i++)
    {
      $path = dirname($path);
    }
    return $path;
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
    foreach($Schema['TABLE'] as $tableName=>$tableCols){
      if( $invert^!in_array($tableName, $tableList) ){
        continue;
      }
      $columns = array();
      // $pattern = "ALTER TABLE \"license_ref\" ADD COLUMN \"rf_pk\" int8;"";
      foreach ($tableCols as $col => $attributes)
      {
        $sql = $attributes["ADD"];
        $alterSql = explode('"', $sql);
        $columns[$alterSql[3]] = "$alterSql[3] " . substr($alterSql[4], 0, -1);
      }
      $createSql = "CREATE TABLE $tableName (" . implode(',', $columns) . ')';
      $this->dbManager->queryOnce($createSql);
    }
  }
  
  /**
   * @param array $tableList
   * @param bool $invert 
   */
  public function insertData($tableList, $invert=FALSE)
  {
    $testdataFile = dirname(__FILE__) . '/testdata.sql';
    $testdata = file_get_contents($testdataFile);
    $delimiter = 'INSERT INTO ';
    $offset = strpos($testdata, $delimiter);
    while( false!==$offset) {
      $nextOffset = strpos($testdata, $delimiter, $offset+1);
      if (false===$nextOffset)
      {
        $sql = substr($testdata, $offset);
      }
      else
      {
        $sql = substr($testdata, $offset, $nextOffset-$offset);
      }
      preg_match('/^INSERT INTO (?P<name>\w+) /', $sql, $table);
      if( ($invert^!in_array($table['name'], $tableList)) ){
        $offset = $nextOffset;
        continue;
      }
      $sql = str_replace(' false,', " 'false',", $sql);
      $sql = str_replace(' true,', " 'true',", $sql);
      $sql = str_replace(' false)', " 'false')", $sql);
      $sql = str_replace(' true)', " 'true')", $sql);
      $this->dbManager->queryOnce($sql);
      $offset = $nextOffset;
    }
  }
  
  public function &getDbManager()
  {
    return $this->dbManager;
  }
}