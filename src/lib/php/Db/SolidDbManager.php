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

class SolidDbManager extends DbManager
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
    $this->cumulatedTime[$statementName] = 0;
    $this->queryCount[$statementName] = 0;
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
    $statement = $this->evaluateStatement($statementName, $params);
    $res = $this->dbDriver->query($statement);
    $execTime = microtime($get_as_float = true) - $startTime;
    $this->collectStatistics($statementName, $execTime);
    $this->logger->addDebug("execution of '$statementName' took " . $this->formatMilliseconds($execTime));
    $this->checkResult($res, "$statementName :: $statement");
    return $res;
  }

  /**
   * @param string $statementName
   * @param array $params
   * @throws \Exception
   * @return resource
   */
  private function evaluateStatement($statementName, $params)
  {
    $sql = $this->preparedStatements[$statementName];
    $cnt = 0;
    foreach($params as $var)
    {
      $cnt++;
      if ($var === null)
      {
        throw new \Exception('given argument for $' . $cnt . ' is null');
      }
      if(is_bool($var))
      {
        $masked = $this->dbDriver->booleanToDb($var);
      }
      else if(is_numeric($var))
      {
        $masked = $var;
      }
      else
      {
        $masked =  "'". $this->dbDriver->escapeString($var)."'";
      }
      $sqlRep = preg_replace('/(\$'.$cnt.')([^\d]|$)/', "$masked$2", $sql);
      if ($sqlRep == $sql)
      {
        throw new \Exception('$' . $cnt . ' not found in prepared statement');
      }
      $sql = $sqlRep;
    }
    if(preg_match('/(\$[\d]+)([^\d]|$)/',$sql, $match)){
      $this->logger->addDebug($match[1]." in '$statementName not resolved");  
    }
    return $sql;
  }
}
