<?php
/*
Copyright (C) 2008-2012 Hewlett-Packard Development Company, L.P.
Copyright (C) 2014, Siemens AG
Author: Johannes Najjar

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

use Fossology\Lib\Db\DbManager;
use Monolog\Logger;

/**
 * Class AgentDao
 * @package Fossology\Lib\Dao
 */
class SpashtDao
{
  /** @var DbManager */
  private $dbManager;

  /**
   * @param DbManager $dbManager
   * @param Logger $logger
   */
  function __construct(DbManager $dbManager, Logger $logger)
  {
    $this->dbManager = $dbManager;
    $this->logger = $logger;
  }

  /**
   * Add new entry into spasht Agent
   */

  public function addComponentRevision($revisionBody, $uploadID){

    $statement = __METHOD__.".AddingNewRevision";

    $params = [
      $revisionBody['body_revision'],
      $revisionBody['body_namespace'],
      $revisionBody['body_name'],
      $revisionBody['body_type'],
      $revisionBody['body_provider'],
      $uploadID
    ];

    $sql = "INSERT INTO spasht ".
    "(spasht_revision, spasht_namespace, spasht_name, spasht_type, spasht_provider, upload_fk)".
    " VALUES($1,$2,$3,$4,$5,$6)";

    $returningValue = "spasht_pk";

    return ($this->dbManager->insertPreparedAndReturn($statement, $sql, $params, $returningValue));
  }

  public function getComponent($uploadID){
    $statement = __METHOD__.".CheckUpload";

    $params = [
      $uploadID
    ];

    $sql = "SELECT * FROM spasht ".
    "WHERE upload_fk = $1";

    $row = $this->dbManager->getSingleRow($sql, $params, $statement);

    /**
     * $row["spasht_revision"] = "spasht_revision";
     * $row["spasht_namespace"] = "spasht_namespace";
     * $row["spasht_name"] = "spasht_name";
     * $row["spasht_type"] = "spasht_type";
     * $row["spasht_provider"] = "spasht_provider";
     * $row["upload_fk"] = "upload_fk";
     */

    return ($row);
  }

  
}
