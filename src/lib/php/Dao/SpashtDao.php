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

  /**
   * Get Spasht ars status for Upload id
   */

  public function getSpashtArs($uploadID)
  {
    $statement = __METHOD__.".CheckUpload";

    $params = [ $uploadID ];

    $sql = "SELECT * FROM spasht_ars ".
    "WHERE upload_fk = $1";

    $row = $this->dbManager->getSingleRow($sql, $params, $statement);
    return ($row);
  }

  /**
   * Perform updates on copyrights from ClearlyDefined
   * @param ItemTreeBounds $item Item
   * @param string $hash         Copyright hash
   * @param string $content      New copyright content
   * @param string $action       Actions ('delete'|'rollback'|nothing=>update)
   */
  public function updateCopyright($item, $hash, $content, $action='')
  {
    $itemTable = $item->getUploadTreeTableName();
    $stmt = __METHOD__ . "$itemTable";
    $params = array($hash, $item->getLeft(), $item->getRight());

    if ($action == "delete") {
      $setSql = "is_enabled='false'";
      $stmt .= '.delete';
    } else if ($action == "rollback") {
      $setSql = "is_enabled='true'";
      $stmt .= '.rollback';
    } else {
      $setSql = "textfinding = $4, hash = md5($4), is_enabled='true'";
      $params[] = $content;
    }

    $sql = "UPDATE copyright_spasht AS cpr SET $setSql
            FROM copyright_spasht as cp
            INNER JOIN $itemTable AS ut ON cp.pfile_fk = ut.pfile_fk
            WHERE cpr.copyright_spasht_pk = cp.copyright_spasht_pk
              AND cp.hash = $1
              AND ( ut.lft BETWEEN $2 AND $3 )";
    if ('uploadtree_a' == $item->getUploadTreeTableName()) {
      $params[] = $item->getUploadId();
      $sql .= " AND ut.upload_fk=$".count($params);
      $stmt .= '.upload';
    }
    $this->dbManager->getSingleRow($sql, $params, $stmt);
  }
}
