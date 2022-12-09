<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Andreas Würl, Steffen Weber

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Db;

interface Driver
{

  /**
   * @param string $statementName
   * @param string $sqlStatement
   * @return resource
   */
  public function prepare($statementName, $sqlStatement);

  /**
   * @param string $statementName
   * @param array $parameters
   * @return resource
   */
  public function execute($statementName, $parameters);

  /**
   * @param string $sqlStatement
   * @return resource
   */
  public function query($sqlStatement);

  /**
   * @return boolean
   */
  public function isConnected();

  /**
   * @return string
   */
  public function getLastError();

  /**
   * @param ressource
   * @return bool
   */
  public function freeResult($res);

  /**
   * @param ressource
   * @return array
   */
  public function fetchArray($res);

  /**
   * @param ressource
   * @return array
   */
  public function fetchAll($res);

  /**
   * @return void
   */
  public function begin();

  /**
   * @return void
   */
  public function commit();

  /**
   * @return void
   */
  public function rollback();

  /**
   * @param $booleanValue
   * @return boolean
   */
  public function booleanFromDb($booleanValue);

  /**
   * @param boolean $booleanValue
   * @return mixed
   */
  public function booleanToDb($booleanValue);

  /**
   * @param string
   * @return string
   */
  public function escapeString($string);

  /**
   * @param string $tableName
   * @return bool
   */
  public function existsTable($tableName);

  /**
   * @param $tableName
   * @param $columnName
   * @return bool
   */
  public function existsColumn($tableName, $columnName);

  /**
   * @param string $stmt
   * @param string $sql
   * @param array $params
   * @param string $colName
   */
  public function insertPreparedAndReturn($stmt, $sql, $params, $colName);
}
