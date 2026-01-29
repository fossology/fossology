<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Proxy;

class LatestScannerProxy extends DbViewProxy
{
  const ARS_SUFFIX = '_ars';
  /** @var int|string */
  private $uploadId;
  /** @var string */
  private $columns = 'agent_pk, agent_name';

  /**
   * @param int|string $uploadId
   * @param array $agentNames used to determine the ars tables
   * @param string $dbViewName
   * @param string $andEnabled
   * @throws \Exception
   * @internal param value $int 0 means non-prepared query
   */
  public function __construct($uploadId, $agentNames=array('nomos','monk'), $dbViewName='latest_scanner', $andEnabled = "AND agent_enabled")
  {
    if (empty($agentNames)) {
      throw new \Exception('empty set of scanners');
    }
    $this->uploadId = $uploadId;
    global $container;
    $dbManager = $container->get('db.manager');
    
    $tableMap = array();
    foreach ($agentNames as $name) {
      $tableMap[strtolower($name . self::ARS_SUFFIX)] = $name;
    }

    $existingTables = $dbManager->getRows(
      "SELECT table_name FROM information_schema.tables 
       WHERE table_catalog = current_database() 
       AND table_schema = 'public' 
       AND table_name = ANY($1::text[])",
      array('{' . implode(',', array_keys($tableMap)) . '}'),
      __METHOD__ . ".checkTables"
    );

    $existingTableNames = array_column($existingTables, 'table_name');
    
    $subqueries = array();
    foreach ($agentNames as $name) {
      $tableName = strtolower($name . self::ARS_SUFFIX);
      if (in_array($tableName, $existingTableNames)) {
        $subqueries[] = "SELECT * FROM (SELECT $this->columns FROM $tableName, agent
          WHERE agent_fk=agent_pk AND upload_fk=$uploadId $andEnabled ORDER BY agent_fk DESC limit 1) latest_$name";
      }
    }
    
    if (empty($subqueries)) {
      $dbViewQuery = "SELECT NULL::int as agent_pk, NULL::text as agent_name WHERE 1=0";
    } else {
      $dbViewQuery = implode(' UNION ',$subqueries);
    }
    parent::__construct($dbViewQuery, $dbViewName."_".implode("_",$agentNames));
  }

  public function materialize()
  {
    if (!is_int($this->uploadId)) {
      throw new \Exception('cannot materialize LatestScannerProxy because upload Id is no number');
    }
    parent::materialize();
  }

  /**
   * @brief create temp table
   */
  public function getNameToIdMap()
  {
    if (!is_int($this->uploadId)) {
      throw new \Exception('cannot map LatestScannerProxy because upload Id is no number');
    }
    global $container;
    $dbManager = $container->get('db.manager');
    $stmt = __METHOD__.".$this->dbViewName";
    if ($this->materialized) {
      $stmt .= '.m';
      $sql = "SELECT * FROM $this->dbViewName";
    } else {
      $sql = $this->dbViewQuery;
    }
    $map = array();

    if (! empty($sql)) {
      $dbManager->prepare($stmt, $sql);
      $res = $dbManager->execute($stmt, array());
      while ($row = $dbManager->fetchArray($res)) {
        $map[$row['agent_name']] = $row['agent_pk'];
      }
      $dbManager->freeResult($res);
    }
    return $map;
  }
}
