<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Test;

// setup autoloading
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/vendor/autoload.php");
require_once(__DIR__ . "/../../../testing/db/TestDbFactory.php");
require (dirname(dirname(__FILE__)).'/common-sysconfig.php');

use Fossology\Lib\Db\Driver\Postgres;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;


class TestPgDb extends TestAbstractDb
{
  /** @var string dbName */
  private $dbName;
  /** @var string logFileName */
  private $logFileName;
  /** @var resource */
  private $connection;
  /** @var array */
  private $sys_conf;

  function __construct($dbName = null, $sysConf = null)
  {
    $dbName = strtolower($dbName);
    $testDbFactory = new \TestDbFactory();
    $this->sys_conf = $sysConf;
    if (empty($this->sys_conf)) {
      $this->sys_conf = $testDbFactory->setupTestDb($dbName);
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

    $this->dbManager = $container->get('db.manager');
    $postgres = new Postgres($this->connection);
    $this->dbManager->setDriver($postgres);
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
    foreach ($tableNames as $row) {
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
    foreach ($tableNames as $row) {
      $name = $row['sequence_name'];
      $this->dbManager->queryOnce("DROP SEQUENCE $name CASCADE",$sqlLog=__METHOD__.".$name");
    }
  }

  function __destruct()
  {
    $this->dbManager = null;
    $this->connection = null;
  }

  function fullDestruct()
  {
    pg_close($this->connection);
    $GLOBALS['PG_CONN'] = false;
    $testDbFactory = new \TestDbFactory();
    $testDbFactory->purgeTestDb($this->sys_conf);
    $this->dbManager = null;
  }

  function isInFossyGroup()
  {
    $gid_array = posix_getgroups();
    foreach ($gid_array as $gid) {
      $gid_info = posix_getgrgid($gid);
      if ($gid_info['name'] === 'fossy') {
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
  public function createPlainTables($tableList, $invert=false)
  {
    $coreSchemaFile = $this->dirnameRec(__FILE__, 4) . '/www/ui/core-schema.dat';
    $Schema = array();
    require($coreSchemaFile);
    foreach ($Schema['TABLE'] as $tableName=>$tableCols) {
      if ($invert^!in_array($tableName, $tableList) || array_key_exists($tableName, $Schema['INHERITS'])) {
        continue;
      }
      // Drop the table first if it exists to avoid "relation already exists" errors
      $this->dbManager->queryOnce("DROP TABLE IF EXISTS \"$tableName\" CASCADE");
      $this->dbManager->queryOnce("CREATE TABLE \"$tableName\" ()");
      $sqlAddArray = array();
      foreach ($tableCols as $attributes) {
        $sqlAdd = preg_replace('/ DEFAULT .*/','',$attributes["ADD"]);
        $sqlAddArray[] = $sqlAdd;
      }
      $this->dbManager->queryOnce(implode(";\n",$sqlAddArray));
    }
  }

  public function resetSequenceAsMaxOf($sequenceName, $tableName, $columnName)
  {
    $this->dbManager->queryOnce("SELECT setval('$sequenceName', (SELECT MAX($columnName) FROM $tableName))");
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

  /**
   * @param string[] $tableList array of table names or empty for all tables
   */
  public function createInheritedTables($tableList=array())
  {
    $table = 'license_candidate';
    if ((empty($tableList) || in_array($table, $tableList)) && !$this->dbManager->existsTable($table)) {
      $this->dbManager->queryOnce("CREATE TABLE $table (group_fk integer,rf_creationdate timestamptz,rf_lastmodified timestamptz,rf_user_fk_created integer,rf_user_fk_modified integer) INHERITS (license_ref)");
    }
    $coreSchemaFile = $this->dirnameRec(__FILE__, 4) . '/www/ui/core-schema.dat';
    $Schema = array();
    require($coreSchemaFile);
    foreach ($Schema['INHERITS'] as $table=>$fromTable) {
      if ($fromTable=='master_ars' || !empty($tableList) && !in_array($table, $tableList) ) {
        continue;
      }
      if (!$this->dbManager->existsTable($table) && $this->dbManager->existsTable($fromTable)) {
        $this->dbManager->queryOnce("CREATE TABLE \"$table\" () INHERITS (\"$fromTable\")");
      }
    }
  }

  public function createInheritedArsTables($agents)
  {
    foreach ($agents as $agent) {
      if (!$this->dbManager->existsTable($agent . '_ars')) {
        $this->dbManager->queryOnce("create table " . $agent . "_ars() inherits(ars_master)");
      }
    }
  }

  /**
   * Populate sysconfig table.
   */
  public function setupSysconfig()
  {
    $this->createPlainTables(['sysconfig'], false);
    $this->createSequences(['sysconfig_sysconfig_pk_seq'], false);
    Populate_sysconfig();
  }
}
