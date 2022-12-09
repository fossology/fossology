<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Proxy;

class DbViewProxy
{
  /** @var string */
  protected $dbViewName;
  /** @var string */
  protected $dbViewQuery;
  /** @var boolean */
  protected $materialized = false;

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
    if ($this->materialized) {
      return;
    }
    global $container;
    $dbManager = $container->get('db.manager');
    $dbManager->queryOnce("CREATE TEMPORARY TABLE $this->dbViewName AS $this->dbViewQuery", "CREATE DbView ".$this->dbViewName);
    $this->materialized = true;
  }

  /**
   * @brief drops temp table
   */
  public function unmaterialize()
  {
    if (!$this->materialized) {
      return;
    }
    global $container;
    $dbManager = $container->get('db.manager');
    $dbManager->queryOnce("DROP TABLE $this->dbViewName", "DROP DbView ".$this->dbViewName);
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
