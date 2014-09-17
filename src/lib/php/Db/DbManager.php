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

class DbManager extends Object
{
  /** * @var Driver */
  private $dbDriver;

  /** * @var array */
  private $preparedStatements;

  /** * @var Logger */
  private $logger;

  /** * @var array */
  private $cumulatedTime = array();

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

  public function begin() {
    $this->dbDriver->begin();
  }

  public function commit() {
    $this->dbDriver->commit();
  }

  /**
   * @param $statementName
   * @param $sqlStatement
   */
  public function prepare($statementName, $sqlStatement)
  {
    if (array_key_exists($statementName, $this->preparedStatements))
    {
      if ($this->preparedStatements[$statementName] !== $sqlStatement)
      {
        Fatal("Existing Statement mismatch: $statementName", __FILE__, __LINE__);
      }
      return;
    }
    $startTime = microtime($get_as_float = true);
    $res = $this->dbDriver->prepare($statementName, $sqlStatement);
    $this->cumulatedTime[$statementName] = microtime($get_as_float = true) - $startTime;
    $this->logger->addDebug("prepare '$statementName' took " . sprintf("%0.3fms", 1000 * $this->cumulatedTime[$statementName]));
    $this->checkResult($res, "$sqlStatement -- $statementName");
    $this->preparedStatements[$statementName] = $sqlStatement;
  }

  /**
   * @param string $statementName statement name
   * @param array $params parameters
   * @return resource
   */
  public function execute($statementName, $params = array())
  {
    if (!array_key_exists($statementName, $this->preparedStatements))
    {
      Fatal("Unknown Statement", __FILE__, __LINE__);
    }
    $startTime = microtime($get_as_float = true);
    $res = $this->dbDriver->execute($statementName, $params);
    $execTime = microtime($get_as_float = true) - $startTime;
    $this->cumulatedTime[$statementName] += $execTime;
    // $this->logger->addDebug("execute '$statementName took " . sprintf("%0.3fms", 1000*$execTime));
    $this->checkResult($res, "$statementName: " . $this->preparedStatements[$statementName] . ' -- -- ' . print_r($params, true));
    return $res;
  }

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
    if ($this->dbDriver->isConnected())
    {
      $this->logger->addCritical($this->dbDriver->getLastError());
    } else
    {
      $this->logger->addCritical("DB connection lost.");
    }
    echo "<br/><pre>$sqlStatement</pre><pre>";
    debug_print_backtrace();
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
    $res = $this->dbDriver->query($sqlStatement);
    $this->logger->addDebug("Query '$sqlLog' took " . sprintf("%0.3fms", 1000 * (microtime($get_as_float = true) - $startTime)));
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
   * @param string tableName
   * @param string keyColumn
   * @param string valueColumn
   * @param string sqlLog
   */
  public function createMap($tableName,$keyColumn,$valueColumn,$sqlLog=''){
  if (empty($sqlLog))
    {
      $sqlLog = __METHOD__ . ".$tableName.$keyColumn,$valueColumn";
    }
    $this->prepare($sqlLog, "select status_pk,meaning from upload_status");
    $res = $this->execute($sqlLog);
    $types = array();
    while ($row = $this->fetchArray($res))
    {
      $types[$row['status_pk']] = $row['meaning'];
    }
    $this->freeResult($res);
    return $types;
  }
  
  public function flushStats()
  {
    foreach ($this->cumulatedTime as $statementName => $seconds)
    {
      $this->logger->addDebug(sprintf(" %0.3fms", 1000 * $seconds) . " for '$statementName' took ");
    }
  }


}