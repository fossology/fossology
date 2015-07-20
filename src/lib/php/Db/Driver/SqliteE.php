<?php
/*
Copyright (C) 2014-2015, Siemens AG
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

namespace Fossology\Lib\Db\Driver;

use Fossology\Lib\Db\Driver;
use SQLite3;
use SQLite3Stmt;

/**
 * SqliteE = Sqlite2.71828182846... < Sqlite3
 */
class SqliteE implements Driver
{
  /** @var Sqlite3 */
  private $dbConnection = false;
  /** @var SQLite3Stmt[] $preparedStmt */
  private $preparedStmt = array();
  /** @var string */
  private $varPrefix = ':var';

  public function __construct($dbConnection)
  {
    $this->dbConnection = $dbConnection;
  }

  /**
   * @param string $statementName
   * @param string $sqlStatement
   * @return resource
   */
  public function prepare($statementName, $sqlStatement)
  {
    $paramCnt = 0;
    $pgStyleVar = '$' . ($paramCnt + 1);
    while (false !== strpos($sqlStatement, $pgStyleVar))
    {
      $paramCnt++;
      $sqlStatement = str_replace($pgStyleVar, $this->varPrefix . chr(64 + $paramCnt), $sqlStatement);
      $pgStyleVar = '$' . ($paramCnt + 1);
      if ($paramCnt == 9) break; // limited number of replaced place holders
    }
    $sqlStatement = str_replace(' ONLY ',' ',$sqlStatement);
    $stmt = $this->dbConnection->prepare($sqlStatement);
    $this->preparedStmt[$statementName] = & $stmt;
    return $stmt;
  }

  /**
   * @param string $statementName
   * @param array $parameters
   * @return resource
   */
  public function execute($statementName, $parameters)
  {
    if (!array_key_exists($statementName, $this->preparedStmt))
    {
      return false;
    }
    $params = array_values($parameters);
    /* @var $stmt SQLite3Stmt */
    $stmt = $this->preparedStmt[$statementName];
    for ($idx = 0; $idx < $stmt->paramCount(); $idx++)
    {
      $variableName = $this->varPrefix . chr(65 + $idx);
      $stmt->bindValue($variableName, $params[$idx]);
    }
    return $stmt->execute();
  }

  /**
   * @param string $sqlStatement
   * @return resource
   */
  public function query($sqlStatement)
  {
    return $this->dbConnection->query($sqlStatement);
  }

  /**
   * @return bool
   */
  public function isConnected()
  {
    return (false !== $this->dbConnection);
  }

  /**
   * @return string
   */
  public function getLastError()
  {
    return SQLite3::lastErrorMsg();
  }

  /**
   * @param SQLite3Result
   * @return bool
   */
  public function freeResult($res)
  {
    return $res->finalize();
  }

  /**
   * @param ressource
   * @return array
   */
  public function fetchArray($res)
  {
    return $res->fetchArray(SQLITE3_ASSOC);
  }

  /**
   * @param ressource
   * @return array
   */
  public function fetchAll($res)
  {
    $result = array();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) // do not SQLITE3_NUM !
    {
      $result[] = $row;
    }
    return $result;
  }

  /**
   * @return void
   */
  public function begin(){
    $this->dbConnection->query("BEGIN");
    return;
  }

  /**
   * @return void
   */
  public function commit(){
    $this->dbConnection->query("COMMIT");
    return;
  }

  /**
   * @return void
   */
  public function rollback(){
    $this->dbConnection->query("ROLLBACK");
    return;
  }

  /**
   * @param $booleanValue
   * @return boolean
   */
  public function booleanFromDb($booleanValue)
  {
    return $booleanValue === 1;
  }

  /**
   * @param boolean $booleanValue
   * @return mixed
   */
  public function booleanToDb($booleanValue)
  {
    return $booleanValue ? 1 : 0;
  }

  /**
   * @param string
   * @return string
   */
  public function escapeString($string)
  {
    return SQLite3::escapeString($string);
  }
  
  /**
   * @param string $tableName
   * @return bool
   */
  public function existsTable($tableName)
  {
    $sql = "SELECT count(*) cnt FROM sqlite_master WHERE type='table' AND name='$tableName'";
    $row = SQLite3::querySingle($sql);
    if (!$row && $this->isConnected())
    {
      throw new \Exception($this->getLastError());
    }
    else if(!$row)
    {
      throw new \Exception('DB connection lost');
    }
    return($row['cnt']>0);
  }
  
  /**
   * @param string $stmt
   * @param string $sql
   * @param array $params
   * @param string $colName
   */
  public function insertPreparedAndReturn($stmt, $sql, $params, $colName)
  {
    $this->prepare($stmt,$sql);
    $res = $this->execute($stmt,$params);
    $this->freeResult($res);
    return SQLiteDatabase::lastInsertRowid();
  }
}
