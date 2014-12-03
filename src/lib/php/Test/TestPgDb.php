<?php
/*
Copyright (C) 2014, Siemens AG

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
require_once(__DIR__ . "/../../../testing/db/TestDbFactory.php");

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Db\Driver\Postgres;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;


class TestPgDb
{
  /** * @var DbManager */
  private $dbManager;
  /** @var string dbName */
  private $dbName;
  /** @var string logFileName */
  private $logFileName;
  /** @var ressource */
  private $connection;
  /** @var array */
  private $sys_conf;

  function __construct($dbName = null)
  {
    $testDbFactory = new \TestDbFactory();
    $this->sys_conf = getenv('TSYSCONFDIR');
    if(empty($this->sys_conf))
    {
      $this->sys_conf = $testDbFactory->setupTestDb($dbName);
      putenv("TSYSCONFDIR=".$this->sys_conf);
      $dbName = $testDbFactory->getDbName($this->sys_conf);
    }
    $this->dbName = $dbName;

    require_once (dirname(dirname(__FILE__)).'/common-db.php');
    $this->connection = DBconnect($this->sys_conf);
    
    require (dirname(dirname(__FILE__)).'/common-container.php');
    global $container;
    $logger = new Logger('default'); // $container->get('logger');
    $this->logFileName = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . 'db.pg.log';
    $logger->pushHandler(new StreamHandler($this->logFileName, Logger::DEBUG));    

    $container->get('db.manager')->setDriver(new Postgres($this->connection));
    $this->dbManager = $container->get('db.manager');
    $this->dbManager->queryOnce("DEALLOCATE ALL");
    $this->dropAllTables();
    $this->dropAllSequences();
  }
  
  public function getFossSysConf()
  {
    return $this->sys_conf;
  }

  private function dropAllTables()
  {
    $this->dbManager->prepare(__METHOD__.'.get',"SELECT table_name FROM information_schema.tables WHERE table_schema=$1 AND table_type=$2");
    $res = $this->dbManager->execute(__METHOD__.'.get',array('public','BASE TABLE'));
    $tableNames = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    foreach($tableNames as $row){
      $name = $row['table_name'];
      $this->dbManager->queryOnce("DROP TABLE IF EXISTS $name CASCADE",$sqlLog=__METHOD__.".$name");
    }
  }

  private function dropAllSequences()
  {
    $this->dbManager->prepare($stmt=__METHOD__.'.get',
            "SELECT sequence_name FROM information_schema.sequences WHERE sequence_schema=$1");
    $res = $this->dbManager->execute($stmt,array('public'));
    $tableNames = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    foreach($tableNames as $row){
      $name = $row['sequence_name'];
      $this->dbManager->queryOnce("DROP SEQUENCE $name CASCADE",$sqlLog=__METHOD__.".$name");
    }
  }
  
  function __destruct()
  {
    $this->dbManager = null;
    if (!pg_close($this->connection))
    {
      throw new \Exception('Could not close connection');
    }
    $this->connection = null;
  }
  
  function fullDestruct()
  {
    $testDbFactory = new \TestDbFactory();
    $testDbFactory->purgeTestDb();
  }

  private function dirnameRec($path, $depth = 1)
  {
    for ($i = 0; $i < $depth; $i++)
    {
      $path = dirname($path);
    }
    return $path;
  }
  
  function isInFossyGroup()
  {
    $gid_array = posix_getgroups();
    foreach($gid_array as $gid)
    {
      $gid_info = posix_getgrgid($gid);
      if ($gid_info['name'] === 'fossy')
      {
        return true;
      }
    }
    $uid = posix_getuid();
    $uid_info = posix_getpwuid($uid);
    return ($uid_info['name'] !== 'root');
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
    foreach($Schema['TABLE'] as $tableName=>$tableCols){
      if( $invert^!in_array($tableName, $tableList) ){
        continue;
      }
      foreach ($tableCols as $attributes)
      {
        $attributeKey = "ALTER";
        if (array_key_exists($attributeKey, $attributes))
          $this->dbManager->queryOnce($attributes[$attributeKey]);
      }
    }
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
      $this->dbManager->queryOnce("CREATE TABLE \"$tableName\" ()");
      foreach ($tableCols as $attributes)
      {
        $this->dbManager->queryOnce($attributes["ADD"]);
      }
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
      $this->dbManager->queryOnce($sql);
      $offset = $nextOffset;
    }
  }
  
  public function insertData_license_ref($limit=140)
  {
    $LIBEXECDIR = $this->dirnameRec(__FILE__, 5) . '/install/db';
    $sqlstmts = file_get_contents("$LIBEXECDIR/licenseref.sql");

    $delimiter = "INSERT INTO license_ref";
    $splitted = explode($delimiter, $sqlstmts);

    for ($i = 1; $i < count($splitted); $i++)
    {
      $sql = $splitted[$i];
      $this->dbManager->queryOnce($delimiter.$sql);
      if ($i > $limit)
      {
        break;
      }
    }
    $this->resetSequenceAsMaxOf('license_ref_rf_pk_seq', 'license_ref', 'rf_pk');
  }

  public function resetSequenceAsMaxOf($sequenceName, $tableName, $columnName)
  {
    $this->dbManager->queryOnce("SELECT setval('$sequenceName', (SELECT MAX($columnName) FROM $tableName))");
  }

  /**
   * @param string $type
   * @param array $elementList
   * @param bool $invert
   */
  private function applySchema($type, $elementList, $invert=FALSE)
  {
    $coreSchemaFile = $this->dirnameRec(__FILE__, 4) . '/www/ui/core-schema.dat';
    $Schema = array();
    require($coreSchemaFile);
    foreach($Schema[$type] as $viewName=>$sql){
      if( $invert^!in_array($viewName, $elementList) ){
        continue;
      }
      $this->dbManager->queryOnce($sql);
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
   * @param array $seqList
   * @param bool $invert
   */
  public function createSequences($seqList, $invert=FALSE)
  {
    $this->applySchema('SEQUENCE', $seqList, $invert);
  }

  /**
   * @param array $cList
   * @param bool $invert
   */
  public function createConstraints($cList, $invert=FALSE)
  {
    $this->applySchema('CONSTRAINT', $cList, $invert);
  }

  public function &getDbManager()
  {
    return $this->dbManager;
  }
  
  public function createInheritedTables()
  {
    if(!$this->dbManager->existsTable('license_candidate'))
    {
      $this->dbManager->queryOnce("CREATE TABLE license_candidate (group_fk integer) INHERITS (license_ref)");
    }
  }
 
}
