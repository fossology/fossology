<?php
/*
Copyright 2019
Author: Vivek Kumar<vvksindia@gmail.com>

Copying and distribution of this file, with or without modification,
are permitted in any medium without royalty provided the copyright
notice and this notice are preserved.  This file is offered as-is,
without any warranty.
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

  public function alterComponentRevision($revisionBody, $uploadID){
    
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

    $params = [$uploadID];

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
