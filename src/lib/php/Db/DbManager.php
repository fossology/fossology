<?php
/*
Copyright (C) 2014, Siemens AG
Authors: Steffen Weber, Andreas Würl

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

namespace Fossology\Lib\Db;

use Fossology\Lib\Util\Object;
use Monolog\Logger;

abstract class DbManager extends Object
{
  /** @var Driver */
  protected $dbDriver;
  /** @var array */
  protected $preparedStatements;
  /** @var Logger */
  protected $logger;
  /** @var array */
  protected $cumulatedTime = array();
  /** @var array */
  protected $queryCount = array();
  /** @var bool */
  private $inTransaction = false;

  function __construct(Logger $logger)
  {
    $this->preparedStatements = array();
    $this->logger = $logger;
  }

  /** param Driver */
  public function setDriver(&$dbDriver)
  {
    $this->dbDriver = $dbDriver;
  }

  /** return Driver */
  public function getDriver()
  {
    return $this->dbDriver;
  }

  /** return boolean */
  public function isInTransaction()
  {
    return $this->inTransaction;
  }

  public function begin() {
    if ($this->inTransaction)
      throw new \Exception("already in transaction");

    $this->dbDriver->begin();
    $this->inTransaction = true;
  }

  public function commit() {
    $this->dbDriver->commit();
    $this->inTransaction = false;
  }

  /**
   * @param $statementName
   * @param $sqlStatement
   * @throws \Exception
   */
  abstract public function prepare($statementName, $sqlStatement);

  /**
   * @param string $statementName statement name
   * @param array $params parameters
   * @throws \Exception
   * @return resource
   */
  abstract public function execute($statementName, $params = array());

  /**
   * @brief Check the result for unexpected errors. If found, treat them as fatal.
   * @param resource $result command result object
   * @param string $sqlStatement SQL command (optional)
   */
  private function checkResult($result, $sqlStatement = "")
  {
    if ($result !== false)
    {
      return;
    }
    $lastError = "";
    if ($this->dbDriver->isConnected())
    {
      $lastError = $this->dbDriver->getLastError();
      $this->logger->addCritical($lastError);
    } else
    {
      $this->logger->addCritical("DB connection lost.");
    }
    echo "<br/><pre>$sqlStatement</pre><pre>";
    debug_print_backtrace();
    print "\n" . $lastError;
    echo "</pre><hr>";
    exit(1);
  }

  /**
   * @param string $sqlStatement
   * @param array $params
   * @param string $statementName (optional)
   * @return array
   */
  public function getSingleRow($sqlStatement, $params = array(), $statementName = "")
  {
    if (empty($statementName))
    {
      $backtrace = debug_backtrace();
      $caller = $backtrace[1];
      $statementName = (array_key_exists('class', $caller) ? "$caller[class]::" : '') . "$caller[function]";
    }
    if (!array_key_exists($statementName, $this->preparedStatements))
    {
      $this->prepare($statementName, $sqlStatement);
    }
    $res = $this->execute($statementName, $params);
    $row = $this->dbDriver->fetchArray($res);
    $this->dbDriver->freeResult($res);
    return $row;
  }

  /**
   * use only for create, begin, commit and injection free queries
   * @param string $sqlStatement
   * @param string $sqlLog sqlStatement
   */
  public function queryOnce($sqlStatement, $sqlLog = '')
  {
    if (empty($sqlLog))
    {
      $sqlLog = $sqlStatement;
    }
    $startTime = microtime($get_as_float = true);
    $execTime = microtime($get_as_float = true) - $startTime;
    $res = $this->dbDriver->query($sqlStatement);
    $this->logger->addDebug("Query '$sqlLog' took " . $this->formatMilliseconds($execTime));
    $this->checkResult($res, $sqlStatement);
    $this->freeResult($res);
  }

  /**
   * @param ressource
   * @return bool
   */
  public function freeResult($res)
  {
    return $this->dbDriver->freeResult($res);
  }

  /**
   * @param ressource
   * @return array
   */
  public function fetchArray($res)
  {
    return $this->dbDriver->fetchArray($res);
  }

  /**
   * @param ressource
   * @return array
   */
  public function fetchAll($res)
  {
    return $this->dbDriver->fetchAll($res);
  }

  /**
   * @param string $tableName
   * @param string $keyColumn
   * @param string $valueColumn
   * @param string $sqlLog
   * @return array
   */
  public function createMap($tableName,$keyColumn,$valueColumn,$sqlLog=''){
    if (empty($sqlLog))
    {
      $sqlLog = __METHOD__ . ".$tableName.$keyColumn,$valueColumn";
    }
    $this->prepare($sqlLog, "select $keyColumn,$valueColumn from $tableName");
    $res = $this->execute($sqlLog);
    $map = array();
    while ($row = $this->fetchArray($res))
    {
      $map[$row[$keyColumn]] = $row[$valueColumn];
    }
    $this->freeResult($res);
    return $map;
  }

  public function flushStats()
  {
    foreach ($this->cumulatedTime as $statementName => $seconds)
    {
      $queryCount = $this->queryCount[$statementName];
      $this->logger->addDebug("executing '$statementName' took "
          . $this->formatMilliseconds($seconds)
          . " ($queryCount queries" . ($queryCount > 0 ? ", avg " . $this->formatMilliseconds($seconds / $queryCount) : "") . ")");
    }
  }

  /**
   * @param $seconds
   * @return string
   */
  protected function formatMilliseconds($seconds)
  {
    return sprintf("%0.3fms", 1000 * $seconds);
  }

  /**
   * @param $statementName
   * @param $execTime
   */
  protected function collectStatistics($statementName, $execTime)
  {
    $this->cumulatedTime[$statementName] += $execTime;
    $this->queryCount[$statementName]++;
  }

  public function booleanFromDb($booleanValue)
  {
    return $this->dbDriver->booleanFromDb($booleanValue);
  }

  public function booleanToDb($booleanValue)
  {
    return $this->dbDriver->booleanToDb($booleanValue);
  }

  /**
   * @param string
   * @param string
   * @param array
   * @param string
   */
  public function insertInto($tableName, $keys,$params,$sqlLog='')
  {
    if (empty($sqlLog))
    {
      $sqlLog = __METHOD__ . ".$tableName.$keys";
    }
    $sql = "INSERT INTO $tableName ($keys) VALUES (";
    $nKeys = substr_count($keys,',')+1;
    for ($i = 1; $i < $nKeys; $i++)
    {
      $sql .= '$'.$i.',';
    }
    $sql .= '$'.$nKeys.')';
    for($i=0;$i<$nKeys;$i++){
      if(is_bool($params[$i]))
      {
        $params[$i] = $this->dbDriver->booleanToDb($params[$i]);
      }
    }
    $this->prepare($sqlLog,$sql);
    $res = $this->execute($sqlLog,$params);
    $this->freeResult($res);
  }

  /**
   * @param string
   * @param array with keys as column names
   * @param string
   */
  public function insertTableRow($tableName,$assocParams,$sqlLog='')
  {
    $params = array_values($assocParams);
    $keys = implode(',',array_keys($assocParams));
    if (empty($sqlLog))
    {
      $sqlLog = __METHOD__ . ".$tableName.$keys";
    }
    $this->insertInto($tableName, $keys, $params, $sqlLog);
  }

  /**
   * @param string $tableName
   * @throws \Exception
   * @return bool
   */
  public function existsTable($tableName)
  {
    if(!preg_match('/^[a-z0-9_]+$/i',$tableName))
    {
      throw new \Exception("invalid table name '$tableName'");
    }
    return $this->dbDriver->existsTable($tableName);
  }
}
