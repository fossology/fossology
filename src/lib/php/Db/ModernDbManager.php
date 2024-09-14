<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Authors: Steffen Weber, Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
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
    if (array_key_exists($statementName, $this->preparedStatements)) {
      echo $this->preparedStatements[$statementName] . "\n";
      echo $sqlStatement;
      if ($this->preparedStatements[$statementName] !== $sqlStatement) {
        throw new \Exception("Existing Statement mismatch: $statementName");
      }
      return;
    }
    $startTime = microtime($get_as_float = true);
    $res = $this->dbDriver->prepare($statementName, $sqlStatement);
    $this->cumulatedTime[$statementName] = microtime($get_as_float = true) - $startTime;
    $this->queryCount[$statementName] = 0;
    $this->logger->debug("prepare '$statementName' took " . sprintf("%0.3fms", 1000 * $this->cumulatedTime[$statementName]));
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
    if (! array_key_exists($statementName, $this->preparedStatements)) {
      throw new \Exception("Unknown Statement");
    }
    $startTime = microtime($get_as_float = true);
    $res = $this->dbDriver->execute($statementName, $params);
    $execTime = microtime($get_as_float = true) - $startTime;
    $this->collectStatistics($statementName, $execTime);
    $this->checkResult($res, "$statementName: " . $this->preparedStatements[$statementName] . ' -- -- ' . print_r($params, true));
    return $res;
  }
}
