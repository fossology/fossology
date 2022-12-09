<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Authors: Steffen Weber, Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Db\Driver;

use Fossology\Lib\Db\Driver;

class Postgres implements Driver
{

  private $dbConnection;

  public function __construct($dbConnection)
  {
    $this->dbConnection = $dbConnection;
  }

  /**
   * @brief PostgreSQL uses no more than NAMEDATALEN-1 characters of an identifier; hence long statementNames
   *        needs to be hashed to be shorter and do not collide due to equivalence of truncated strings
   * @param string $stmt
   * @return string
   */
  private function identifierHash($stmt)
  {
    $namedatalen = 63;
    if ($namedatalen >= strlen($stmt)) {
      return $stmt;
    }
    $hash = substr($stmt, 0, $namedatalen);
    for ($i = $namedatalen; $i < strlen($stmt); $i ++) {
      $hash[$i%$namedatalen] = chr((ord($hash[$i%$namedatalen])+ord($stmt[$i])-32)%96+32);
    }
    return $hash;
  }

  /**
   * @param string $statementName
   * @param string $sqlStatement
   * @return resource
   */
  public function prepare($statementName, $sqlStatement)
  {
    return pg_prepare($this->dbConnection, $this->identifierHash($statementName), $sqlStatement);
  }

  /**
   * @param string $statementName
   * @param array $parameters
   * @return resource
   */
  public function execute($statementName, $parameters)
  {
    return pg_execute($this->dbConnection, $this->identifierHash($statementName), $parameters);
  }

  /**
   * @param string $sqlStatement
   * @return resource
   */
  public function query($sqlStatement)
  {
    return pg_query($this->dbConnection, $sqlStatement);
  }

  /**
   * @return bool
   */
  public function isConnected()
  {
    return pg_connection_status($this->dbConnection) === PGSQL_CONNECTION_OK;
  }

  /**
   * @return string
   */
  public function getLastError()
  {
    return pg_last_error($this->dbConnection);
  }

  /**
   * @param ressource
   * @return bool
   */
  public function freeResult($res)
  {
    return pg_free_result($res);
  }

  /**
   * @param ressource
   * @return array
   */
  public function fetchArray($res)
  {
    return pg_fetch_array($res, null, PGSQL_ASSOC);
  }

  /**
   * @param ressource
   * @return array
   */
  public function fetchAll($res)
  {
    if (pg_num_rows($res) == 0) {
      return array();
    }
    return pg_fetch_all($res);
  }

  /**
   * @return void
   */
  public function begin()
  {
    pg_query($this->dbConnection, "BEGIN");
    return;
  }

  /**
   * @return void
   */
  public function commit()
  {
    pg_query($this->dbConnection, "COMMIT");
    return;
  }

  /**
   * @return void
   */
  public function rollback()
  {
    pg_query($this->dbConnection, "ROLLBACK");
    return;
  }

  /**
   * @param $booleanValue
   * @return boolean
   */
  public function booleanFromDb($booleanValue)
  {
    return $booleanValue === 't';
  }

  /**
   * @param boolean $booleanValue
   * @return mixed
   */
  public function booleanToDb($booleanValue)
  {
    return $booleanValue ? 't' : 'f';
  }

  /**
   * @param string
   * @return string
   */
  public function escapeString($string)
  {
    return pg_escape_string($string);
  }

  /**
   * @param string $tableName
   * @throws \Exception
   * @return bool
   */
  public function existsTable($tableName)
  {
    $dbName = pg_dbname($this->dbConnection);
    $sql = "SELECT count(*) cnt
              FROM information_schema.tables
             WHERE table_catalog='$dbName'
               AND table_name='". strtolower($tableName) . "'";
    $res = pg_query($this->dbConnection, $sql);
    if (!$res && pg_connection_status($this->dbConnection) === PGSQL_CONNECTION_OK) {
      throw new \Exception(pg_last_error($this->dbConnection));
    } else if (! $res) {
      throw new \Exception('DB connection lost');
    }
    $row = pg_fetch_assoc($res);
    pg_free_result($res);
    return($row['cnt']>0);
  }

  /**
   * @param $tableName
   * @param $columnName
   * @throws \Exception
   * @return bool
   */
  public function existsColumn($tableName, $columnName)
  {
    $dbName = pg_dbname($this->dbConnection);
    $sql = "SELECT count(*) cnt
              FROM information_schema.columns
             WHERE table_catalog='$dbName'
               AND table_name='". strtolower($tableName) . "'
               AND column_name='". strtolower($columnName) . "'";
    $res = pg_query($this->dbConnection, $sql);
    if (!$res && pg_connection_status($this->dbConnection) === PGSQL_CONNECTION_OK) {
      throw new \Exception(pg_last_error($this->dbConnection));
    } else if (! $res) {
      throw new \Exception('DB connection lost');
    }
    $row = pg_fetch_assoc($res);
    pg_free_result($res);
    return($row['cnt']>0);
  }

  /**
   * @param string $stmt
   * @param string $sql
   * @param array $params
   * @param string $colName
   * @return mixed
   */
  public function insertPreparedAndReturn($stmt, $sql, $params, $colName)
  {
    $sql .= " RETURNING $colName";
    $stmt .= ".returning:$colName";
    $this->prepare($stmt,$sql);
    $res = $this->execute($stmt,$params);
    $return = $this->fetchArray($res);
    $this->freeResult($res);
    return $return[$colName];
  }
}
