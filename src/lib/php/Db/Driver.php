<?php
/*
Copyright (C) 2014, Siemens AG
Author: Andreas Würl, Steffen Weber

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
   * @param string $stmt
   * @param string $sql
   * @param array $params
   * @param string $colName
   */
  public function insertPreparedAndReturn($stmt, $sql, $params, $colName);
}
