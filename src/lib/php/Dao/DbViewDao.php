<?php
/*
Copyright (C) 2014, Siemens AG

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

namespace Fossology\Lib\Dao;

class DbViewDao
{
  /** @var string */
  private $dbViewName;
  /** @var string */
  private $dbViewQuery;
  private $materialized = false;
  
  /**
   * @param string $dbViewQuery
   * @param string $dbViewName
   */
  public function __construct($dbViewQuery, $dbViewName)
  {
    $this->dbViewQuery = $dbViewQuery;
    $this->dbViewName = $dbViewName;
  }
  
  /**
   * @return string
   */
  public function getDbViewName()
  {
    return $this->dbViewName;
  }

  /**
   * @brief create temp table
   */
  public function materialize()
  {
    if ($this->materialized)
    {
      return;
    }
    global $container;
    $dbManager = $container->get('db.manager');
    $dbManager->queryOnce("CREATE TEMPORARY TABLE $this->dbViewName AS $this->dbViewQuery");
    $this->materialized = true;
  }

  /**
   * @brief drops temp table
   */
  public function unmaterialize()
  {
    if (!$this->materialized)
    {
      return;
    }
    global $container;
    $dbManager = $container->get('db.manager');
    $dbManager->queryOnce("DROP TABLE $this->dbViewName");
    $this->materialized = false;
  }    
    
  /**
   * @brief Common Table Expressions
   * @return string
   */
  public function asCTE()
  {
    return "WITH $this->dbViewName AS (".$this->dbViewQuery.")";
  }
  
  public function getDbViewQuery()
  {
    return $this->dbViewQuery;
  }

}