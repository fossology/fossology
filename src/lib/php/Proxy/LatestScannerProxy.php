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
    $subqueries = array();
    foreach ($agentNames as $name) {
      // NOTE: this query fails if the ars-table is not yet created.
      $subqueries[] = "SELECT * FROM (SELECT $this->columns FROM $name".self::ARS_SUFFIX.", agent
        WHERE agent_fk=agent_pk AND upload_fk=$uploadId $andEnabled ORDER BY agent_fk DESC limit 1) latest_$name";
    }
    $dbViewQuery = implode(' UNION ',$subqueries);
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
