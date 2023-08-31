<?php
/*
 SPDX-FileCopyrightText: © 2019 Vivek Kumar
 Author: Vivek Kumar<vvksindia@gmail.com>
 SPDX-FileCopyrightText: © 2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Data\Package\ComponentType;
use Fossology\Lib\Data\Spasht\Coordinate;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\PurlOperations;
use Monolog\Logger;
use Fossology\Lib\Util\StringOperation;

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
   *
   * @param Coordinate $coordinate Coordinates
   * @param integer $uploadID      Upload ID
   * @return integer New id
   */
  public function addComponentRevision($coordinate, $uploadID)
  {
    $statement = __METHOD__.".AddingNewRevision";

    $keys = "spasht_type,spasht_provider,spasht_namespace,spasht_name," .
      "spasht_revision,upload_fk";

    $params = [
      $coordinate->getType(),
      $coordinate->getProvider(),
      $coordinate->getNamespace(),
      $coordinate->getName(),
      $coordinate->getRevision(),
      $uploadID
    ];

    return $this->dbManager->insertInto("spasht", $keys, $params, $statement,
      "spasht_pk");
  }

  /**
   * Update the component coordinates in DB
   *
   * @param Coordinate $coordinate New coordinates
   * @param integer $uploadID      Upload to update
   * @return integer Upload ID on success
   */
  public function alterComponentRevision($coordinate, $uploadID)
  {
    $assocParams = [
      "spasht_type" => $coordinate->getType(),
      "spasht_provider" => $coordinate->getProvider(),
      "spasht_namespace" => $coordinate->getNamespace(),
      "spasht_name" => $coordinate->getName(),
      "spasht_revision" => $coordinate->getRevision()
    ];

    $tableName = "spasht";
    $primaryColumn = 'upload_fk';

    $this->dbManager->updateTableRow($tableName, $assocParams, $primaryColumn,
      $uploadID, __METHOD__ . ".updateCoordinates");
    return $uploadID;
  }

  /**
   * Get available coordinate in spasht where uploadId is found.
   * @param integer $uploadID Upload to search
   * @return Coordinate Coordinate, if found. NULL otherwise.
   */
  public function getComponent($uploadID)
  {
    $statement = __METHOD__.".CheckUpload";

    $params = [ $uploadID ];

    $sql = "SELECT * FROM spasht ".
      "WHERE upload_fk = $1";

    $row = $this->dbManager->getSingleRow($sql, $params, $statement);
    if (empty($row)) {
      return null;
    }
    return $this->rowToCoordinate($row);
  }

  /**
   * Get Spasht ars status for Upload id
   * @params integer $uploadId Upload to get information from
   * @return array Row from ARS table
   */
  public function getSpashtArs($uploadID)
  {
    $statement = __METHOD__.".CheckUpload";

    $params = [ $uploadID ];

    $sql = "SELECT * FROM spasht_ars ".
      "WHERE upload_fk = $1 ORDER BY ars_pk DESC;";

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
      $params[] = StringOperation::replaceUnicodeControlChar($content);
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

  /**
   * Translate row from DB to Coordinate object
   * @param array $row Row from DB
   * @return Fossology::Lib::Data::Spasht::Coordinate Coordinate object
   */
  private function rowToCoordinate($row)
  {
    $obj = [
      'type' => $row['spasht_type'],
      'provider' => $row['spasht_provider'],
      'namespace' => $row['spasht_namespace'],
      'name' => $row['spasht_name'],
      'revision' => $row['spasht_revision']
    ];
    return new Coordinate($obj);
  }

  /**
   * @brief Get ClearlyDefined Coordinate if component id is a pURL.
   *
   * Read component id from Database. If empty or is not a pURL, return NULL.
   * Otherwise parse the pURL and create a Coordinate from it.
   *
   * @note pURL does not have type and provide, so they both get same value in
   *       coordinate.
   *
   * @param integer $uploadId Upload to get Coordinate from.
   * @return null|Coordinate Return null if not pURL, Coordinate otherwise.
   */
  public function getCoordinateFromCompId($uploadId)
  {
    $sql = "SELECT ri_component_type, ri_component_id " .
      "FROM report_info WHERE upload_fk = $1;";
    $compRow = $this->dbManager->getSingleRow($sql, [$uploadId]);
    if (
      empty($compRow) || empty($compRow['ri_component_id'])
      || $compRow['ri_component_id'] == "NA"
      || (
        $compRow['ri_component_type'] != ComponentType::PURL
        && $compRow['ri_component_type'] != ComponentType::PACKAGEURL
        )
    ) {
      return null;
    }
    $componentId = $compRow['ri_component_id'];
    $purl = PurlOperations::fromString($componentId);
    if ($purl["scheme"] != "pkg") {
      return null;
    }
    return new Coordinate([
      "type" => $purl["type"],
      "provider" => $purl["type"],
      "namespace" => $purl["namespace"],
      "name" => $purl["name"],
      "revision" => $purl["version"]
    ]);
  }
}
