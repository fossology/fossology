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

use Monolog\Logger;

class ModernDbManager extends DbManager
{
  function __construct(Logger $logger)
  {
    parent::__construct($logger);
  }

  /**
   * @param $statementName
   * @param $sqlStatement
   * @throws \Exception
   */
  public function prepare($statementName, $sqlStatement)
  {
    if (array_key_exists($statementName, $this->preparedStatements))
    {
      if ($this->preparedStatements[$statementName] !== $sqlStatement)
      {
        throw new \Exception("Existing Statement mismatch: $statementName");
      }
      return;
    }
    $startTime = microtime($get_as_float = true);
    $res = $this->dbDriver->prepare($statementName, $sqlStatement);
    $this->cumulatedTime[$statementName] = microtime($get_as_float = true) - $startTime;
    $this->queryCount[$statementName] = 0;
    $this->logger->addDebug("prepare '$statementName' took " . sprintf("%0.3fms", 1000 * $this->cumulatedTime[$statementName]));
    $this->checkResult($res, "$sqlStatement -- $statementName");
    $this->preparedStatements[$statementName] = $sqlStatement;
  }

  /**
   * @param string $statementName statement name
   * @param array $params parameters
   * @throws \Exception
   * @return resource
   */
  public function execute($statementName, $params = array())
  {
    if (!array_key_exists($statementName, $this->preparedStatements))
    {
      throw new \Exception("Unknown Statement");
    }
    $startTime = microtime($get_as_float = true);
    $res = $this->dbDriver->execute($statementName, $params);
    $execTime = microtime($get_as_float = true) - $startTime;
    $this->collectStatistics($statementName, $execTime);
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
}
