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

}
