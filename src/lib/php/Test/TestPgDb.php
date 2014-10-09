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
    date_default_timezone_set("UTC");
    if (!is_callable('pg_connect')) {
      throw new \Exception("php-psql not found");
    }
    if (!isset($dbName))
    {
      $sub = chr(mt_rand(97,122)).chr(mt_rand(97,122)).chr(mt_rand(97,122)).chr(mt_rand(97,122));
      $dbName = "fosstest".time().$sub;
    }
    $this->dbName = $dbName;
    $this->ensurePgPassFileEntry();
    
    $this->sys_conf = sys_get_temp_dir()."/$dbName";
    if(!mkdir($this->sys_conf,$mode=0755))
    {
      throw new \Exception("FATAL! Cannot create test repository at ".$this->sys_conf);
    }
    if(chmod($this->sys_conf, 0755) === FALSE )
    {
      echo "ERROR: Cannot set mode to 755 on ".$this->sys_conf."\n" . __FILE__ . " at line " . __LINE__ . "\n";
    }
    $conf = "dbname=$dbName;\nhost=localhost;\nuser=fossy;\npassword=fossy;\n";
    if(file_put_contents($this->sys_conf . "/Db.conf", $conf) === FALSE)
    {
      throw new \Exception("FATAL! Could not create Db.conf file at ".$this->sys_conf);
    }

    exec($cmd="psql -Ufossy -l | grep -q $dbName", $cmdOut, $cmdRtn);
    if($cmdRtn == 0)
    {
      echo "NOTE: $dbName database already exists, not creating";
      exec($cmd="createlang -Ufossy -l $dbName | grep -q plpgsql", $cmdOut, $cmdRtn);
      if($cmdRtn == 0)
        echo "NOTE: plpgsql already exists in $dbName database\n";
      else
      {
        exec($cmd="createlang -Ufossy plpgsql $dbName", $cmdOut, $cmdRtn);
        if($cmdRtn != 0)
          throw new \Exception("ERROR: failed to add plpgsql to $dbName database");
      }
    }    
    else
    {
      $fosstestSql = file_get_contents( dirname(__FILE__).'/fosstestinit.sql');
      $fossSql = str_replace('fosstest',$dbName,$fosstestSql);
      $pathSql = $this->sys_conf.'/dbinit.sql';
      file_put_contents($pathSql,$fossSql);
      exec($cmd="psql -Ufossy fossology < $pathSql", $cmdOut, $cmdRtn); //  2>&1
      if ($cmdRtn != 0)
      {
        throw new \Exception("ERROR: Database failed during configuration.");
      }
      unlink($pathSql);
    }

    require_once (dirname(dirname(__FILE__)).'/common-db.php');
    $this->connection = DBconnect($this->sys_conf);
    
    require (dirname(dirname(__FILE__)).'/common-container.php');
    global $container;
    // $logger = $container->get('logger');
    $logger = new Logger('default');
    $this->logFileName = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . 'db.pg.log';
    $logger->pushHandler(new StreamHandler($this->logFileName, Logger::DEBUG));    

    $container->get('db.manager')->setDriver(new Postgres($this->connection));
    $this->dbManager = $container->get('db.manager');
  }
  
  public function ensurePgPassFileEntry()
  {
    $userHome = getenv('HOME');
    $ipv4 = gethostbyname(gethostname());
    $fullHostName = gethostbyaddr(gethostbyname($ipv4));
    $contents = "$fullHostName:*:*:fossy:fossy\n";
    $pgpass = "$userHome/.pgpass";
    putenv("PGPASSFILE=$pgpass");
    $pg_pass_contents = file_exists($pgpass) ? file_get_contents($pgpass) : '';
    if (!preg_match('/\:fossy\:fossy/', $pg_pass_contents)) {
      $pgpassHandle = fopen($pgpass,'w');
      $howmany = fwrite($pgpassHandle, $contents);
      if($howmany === FALSE)
      {
        throw new \Exception("FATAL! Could not write .pgpass file to $pgpassHandle" );
      }
      fclose($pgpassHandle);
    }
    if(!chmod($pgpass, 0600))
    {
      echo "Warning! could not set $pgpass to 0600\n";
    }
  }
  
  function __destruct()
  {
    $this->dbManager = null;
    if(!pg_close($this->connection))
      throw new \Exception('Could not close connection');
    $this->connection = null;
    
    $existCmd = "psql -Ufossy -l | grep -q ".$this->dbName;
    exec($existCmd, $existkOut, $existRtn);
    if($existRtn == 0)
    {
      $dropCmd = "dropdb -Ufossy ".$this->dbName;
      exec($dropCmd, $dropOut, $dropRtn);
      if($dropRtn != 0 )
      {
        echo("ERROR: failed to delete database ".$this->dbName);
      }
    }
    else
    {
      echo "NOTE: database ".$this->dbName." does not exist, nothing to delete\n";
    }

    /* 
     */
    foreach (glob($this->sys_conf."/*.*") as $filename) {
      unlink($filename);
    rmdir($this->sys_conf);
}
    
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
//        $this->dbManager->queryOnce($attributes["ALTER"]);
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
      $this->dbManager->queryOnce($sql);
      if ($i > $limit)
      {
        break;
      }
    }
  }

  /**
   * @param array $viewList
   * @param bool $invert 
   */
  public function createViews($viewList, $invert=FALSE)
  {
    $coreSchemaFile = $this->dirnameRec(__FILE__, 4) . '/www/ui/core-schema.dat';
    $Schema = array();
    require($coreSchemaFile);
    foreach($Schema['VIEW'] as $viewName=>$sql){
      if( $invert^!in_array($viewName, $viewList) ){
        continue;
      }
      $this->dbManager->queryOnce($sql);
    }
  }
  
  public function &getDbManager()
  {
    return $this->dbManager;
  }
}