<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Authors: Steffen Weber, Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Db;

use Fossology\Lib\Exception;
use Monolog\Logger;

abstract class DbManager
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
  /** @var int */
  private $transactionDepth = 0;

  function __construct(Logger $logger)
  {
    $this->preparedStatements = array();
    $this->logger = $logger;
  }

  /** param Driver */
  public function setDriver(Driver &$dbDriver)
  {
    $this->dbDriver = $dbDriver;
  }

  /** return Driver */
  public function getDriver()
  {
    return $this->dbDriver;
  }

  public function begin()
  {
    if ($this->transactionDepth==0) {
      $this->dbDriver->begin();
    }
    $this->transactionDepth++;
  }

  public function commit()
  {
    $this->transactionDepth--;
    if ($this->transactionDepth==0) {
      $this->dbDriver->commit();
    } else if ($this->transactionDepth < 0) {
      throw new \Exception('too much transaction commits');
    }
  }

  public function rollback()
  {
    if ($this->transactionDepth > 0) {
      $this->transactionDepth--;
      $this->dbDriver->rollback();
    } else if ($this->transactionDepth == 0) {
      throw new \Exception('too much transaction rollbacks');
    }
  }

  /**
   * @param $statementName
   * @param $sqlStatement
   * @throws \Exception
   */
  abstract public function prepare($statementName, $sqlStatement);

  /**
   * Note: this builds a query which is not useable with SQLite
   * one should use SqLiteE::insertPreparedAndReturn() instead
   *
   * @param $statementName
   * @param $sqlStatement
   * @param $params
   * @param $returning
   * @return mixed
   */
  public function insertPreparedAndReturn($statementName, $sqlStatement, $params, $returning)
  {
    $sqlStatement .= " RETURNING $returning";
    $statementName .= ".returning:$returning";
    $this->prepare($statementName,$sqlStatement);
    $res = $this->execute($statementName,$params);
    $return = $this->fetchArray($res);
    $this->freeResult($res);
    return $return[$returning];
  }

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
  protected function checkResult($result, $sqlStatement = "")
  {
    if ($result !== false) {
      return;
    }
    $lastError = "";
    if ($this->dbDriver->isConnected()) {
      $lastError = $this->dbDriver->getLastError();
      $this->logger->critical($lastError);
      if ($this->transactionDepth>0) {
        $this->dbDriver->rollback();
      }
    } else {
      $this->logger->critical("DB connection lost.");
    }

    $message = "error executing: $sqlStatement\n\n$lastError";
    throw new Exception($message);
  }

  /**
   * @param string $sqlStatement
   * @param array $params
   * @param string $statementName (optional)
   * @return array
   */
  public function getSingleRow($sqlStatement, $params = array(), $statementName = "")
  {
    if (empty($statementName)) {
      $backtrace = debug_backtrace();
      $caller = $backtrace[1];
      $statementName = (array_key_exists('class', $caller) ? "$caller[class]::" : '') . "$caller[function]";
    }
    if (!array_key_exists($statementName, $this->preparedStatements)) {
      $this->prepare($statementName, $sqlStatement);
    }
    $res = $this->execute($statementName, $params);
    $row = $this->dbDriver->fetchArray($res);
    $this->dbDriver->freeResult($res);
    return $row;
  }

  /**
   * @param $sqlStatement
   * @param array $params
   * @param string $statementName
   * @return array
   */
  public function getRows($sqlStatement, $params = array(), $statementName = "")
  {
    if (empty($statementName)) {
      $backtrace = debug_backtrace();
      $caller = $backtrace[1];
      $statementName = (array_key_exists('class', $caller) ? "$caller[class]::" : '') . "$caller[function]";
    }
    if (!array_key_exists($statementName, $this->preparedStatements)) {
      $this->prepare($statementName, $sqlStatement);
    }
    $res = $this->execute($statementName, $params);
    $rows = $this->dbDriver->fetchAll($res);
    $this->dbDriver->freeResult($res);
    return $rows;
  }

  /**
   * use only for create, begin, commit and injection free queries
   * @param string $sqlStatement
   * @param string $sqlLog sqlStatement
   */
  public function queryOnce($sqlStatement, $sqlLog = '')
  {
    if (empty($sqlLog)) {
      $sqlLog = $sqlStatement;
    }
    $startTime = microtime($get_as_float = true);
    $res = $this->dbDriver->query($sqlStatement);
    $this->checkResult($res, $sqlStatement);
    $this->freeResult($res);
    $execTime = microtime($get_as_float = true) - $startTime;
    $this->logger->debug("query '$sqlLog' took " . $this->formatMilliseconds($execTime));
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
  public function createMap($tableName,$keyColumn,$valueColumn,$sqlLog='')
  {
    if (empty($sqlLog)) {
      $sqlLog = __METHOD__ . ".$tableName.$keyColumn,$valueColumn";
    }
    $this->prepare($sqlLog, "select $keyColumn,$valueColumn from $tableName");
    $res = $this->execute($sqlLog);
    $map = array();
    while ($row = $this->fetchArray($res)) {
      $map[$row[$keyColumn]] = $row[$valueColumn];
    }
    $this->freeResult($res);
    return $map;
  }

  public function flushStats()
  {
    foreach ($this->cumulatedTime as $statementName => $seconds) {
      $queryCount = $this->queryCount[$statementName];
      $this->logger->debug("executing '$statementName' took "
          . $this->formatMilliseconds($seconds)
          . " ($queryCount queries" . ($queryCount > 0 ? ", avg " . $this->formatMilliseconds($seconds / $queryCount) : "") . ")");
    }

    if ($this->transactionDepth != 0) {
      throw new \Fossology\Lib\Exception("you have not committed enough");
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

  private function cleanupParamsArray($params)
  {
    $nParams = sizeof($params);
    for ($i = 0; $i<$nParams; $i++) {
      if (is_bool($params[$i])) {
        $params[$i] = $this->dbDriver->booleanToDb($params[$i]);
      }
    }
    return $params;
  }

  /**
   * @param $tableName
   * @param $keys
   * @param $params
   * @param string $sqlLog
   * @param string $returning
   * @return mixed|void
   */
  public function insertInto($tableName, $keys, $params, $sqlLog='', $returning='')
  {
    if (empty($sqlLog)) {
      $sqlLog = __METHOD__ . ".$tableName.$keys" . (empty($returning) ? "" : md5($returning));
    }
    $sql = "INSERT INTO $tableName ($keys) VALUES (";
    $nKeys = substr_count($keys,',')+1;
    for ($i = 1; $i < $nKeys; $i++) {
      $sql .= '$'.$i.',';
    }
    $sql .= '$'.$nKeys.')';
    $params = $this->cleanupParamsArray($params);
    if (!empty($returning)) {
      return $this->insertPreparedAndReturn($sqlLog, $sql, $params, $returning);
    }
    $this->prepare($sqlLog,$sql);
    $res = $this->execute($sqlLog,$params);
    $this->freeResult($res);
  }

  /**
   * @param $tableName
   * @param array $assocParams array with keys as column names
   * @param string $sqlLog
   * @param string $returning column that should be returned (empty string if not required)
   * @return mixed|null
   */
  public function insertTableRow($tableName,$assocParams,$sqlLog='',$returning='')
  {
    $params = array_values($assocParams);
    $keys = implode(',',array_keys($assocParams));
    if (empty($sqlLog)) {
      $sqlLog = __METHOD__ . ".$tableName.$keys" . (empty($returning) ? "" : md5($returning));
    }
    return $this->insertInto($tableName, $keys, $params, $sqlLog, $returning);
  }

  public function updateTableRow($tableName, $assocParams, $idColName, $id, $sqlLog='')
  {
    $params = array_values($assocParams);
    $keys = array_keys($assocParams);
    $nKeys = sizeof($keys);

    if (empty($sqlLog)) {
      $sqlLog = __METHOD__ . ".$tableName." . implode(",", $keys);
    }

    $sql = "UPDATE $tableName SET";
    for ($i = 1; $i < $nKeys; $i++) {
      $sql .= " ".$keys[$i - 1].' = $'.$i.",";
    }
    $sql .= " ".$keys[$nKeys - 1].' = $'.$nKeys;
    $sql .= " WHERE $idColName = \$".($nKeys + 1);

    $params[] = $id;
    $params = $this->cleanupParamsArray($params);

    $this->prepare($sqlLog,$sql);
    $res = $this->execute($sqlLog,$params);
    $this->freeResult($res);
  }

  /**
   * @param string $tableName
   * @throws \Exception
   * @return bool
   */
  public function existsTable($tableName)
  {
    if (! preg_match('/^[a-z0-9_]+$/i',$tableName)) {
      throw new \Exception("invalid table name '$tableName'");
    }
    return $this->dbDriver->existsTable($tableName);
  }

  /**
   * @param $tableName
   * @param $columnName
   * @throws \Exception
   * @return bool
   */
  public function existsColumn($tableName, $columnName)
  {
    if (! preg_match('/^[a-z0-9_]+$/i',$columnName)) {
      throw new \Exception("invalid column name '$columnName'");
    }
    return $this->existsTable($tableName) && $this->dbDriver->existsColumn($tableName, $columnName);
  }
}
