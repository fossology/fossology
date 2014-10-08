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

namespace Fossology\Lib\Db\Driver;

use Fossology\Lib\Db\Driver;
use SQLite3;
use SQLite3Stmt;

/**
 * SqliteE = Sqlite2.71828182846... < Sqlite3
 */
class SqliteE implements Driver
{
  /**
   * @var Sqlite3
   */
  private $dbConnection = false;

  /**
   * @var SQLite3Stmt[] $preparedStmt
   */
  private $preparedStmt = array();

  /**
   *
   * @var string
   */
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
    /**
     * @var SQLite3Stmt
     */
    $stmt = $this->preparedStmt[$statementName];
    for ($idx = 0; $idx < $stmt->paramCount(); $idx++)
    {
      $stmt->bindValue($this->varPrefix . chr(65 + $idx), $params[$idx]);
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
    return $res->fetchArray();
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
}
