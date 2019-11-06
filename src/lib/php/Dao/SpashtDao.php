<?php
/*
Copyright (C) 2019
Author: Vivek Kumar<vvksindia@gmail.com>

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

  public function addComponentRevision($revisionBody, $uploadID)
  {
    $statement = __METHOD__.".AddingNewRevision";

    $params = [ $revisionBody['body_revision'], $revisionBody['body_namespace'], $revisionBody['body_name'], $revisionBody['body_type'], $revisionBody['body_provider'], $uploadID ];

    $sql = "INSERT INTO spasht ".
    "(spasht_revision, spasht_namespace, spasht_name, spasht_type, spasht_provider, upload_fk)".
    " VALUES($1,$2,$3,$4,$5,$6)";

    $returningValue = "spasht_pk";

    return ($this->dbManager->insertPreparedAndReturn($statement, $sql, $params, $returningValue));
  }

  public function alterComponentRevision($revisionBody, $uploadID)
  {
    $assocParams = array('spasht_namespace' => $revisionBody['body_namespace'], 'spasht_name' => $revisionBody['body_name'],
    'spasht_type' => $revisionBody['body_type'], 'spasht_provider' => $revisionBody['body_provider'],
    'spasht_revision' => $revisionBody['body_revision']);

    $tableName = "spasht";
    $primaryColumn = 'upload_fk';

    $this->dbManager->updateTableRow($tableName, $assocParams, $primaryColumn, $uploadID);
    return $uploadID;
  }

  /**
   * Get available row in spasht.
   * Where uploadId is found.
   */

  public function getComponent($uploadID)
  {
    $statement = __METHOD__.".CheckUpload";

    $params = [ $uploadID ];

    $sql = "SELECT * FROM spasht ".
    "WHERE upload_fk = $1";

    $row = $this->dbManager->getSingleRow($sql, $params, $statement);
    return ($row);
  }
}
