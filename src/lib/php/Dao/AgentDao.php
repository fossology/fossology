<?php
/*
 SPDX-FileCopyrightText: © 2008-2012 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Db\DbManager;
use Monolog\Logger;

/**
 * Class AgentDao
 * @package Fossology\Lib\Dao
 */
class AgentDao
{
  const ARS_TABLE_SUFFIX = "_ars";

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

  public function arsTableExists($agentName)
  {
    return $this->dbManager->existsTable($this->getArsTableName($agentName));
  }

  public function createArsTable($agentName)
  {
    $tableName = $this->getArsTableName($agentName);
    $this->dbManager->queryOnce("CREATE TABLE ".$tableName."() INHERITS(ars_master);
    ALTER TABLE ONLY ".$tableName." ADD CONSTRAINT ".$tableName."_agent_fk_fkc FOREIGN KEY (agent_fk) REFERENCES agent(agent_pk);
    ALTER TABLE ONLY ".$tableName." ADD CONSTRAINT ".$tableName."_upload_fk_fkc FOREIGN KEY (upload_fk) REFERENCES upload(upload_pk) ON DELETE CASCADE", __METHOD__);
  }

  public function writeArsRecord($agentName,$agentId,$uploadId,$arsId=0,$success=false,$status="")
  {
    $arsTableName = $this->getArsTableName($agentName);

    if ($arsId) {
      $successDb = $this->dbManager->booleanToDb($success);
      $parms = array($successDb, $arsId);

      $stmt = __METHOD__.".$arsTableName";

      if (!empty($status)) {
        $stmt .= ".status";
        $parms[] = $status;
        $statusClause = ", ars_status = $".count($parms);
      } else {
        $statusClause = "";
      }

      $this->dbManager->getSingleRow(
              "UPDATE $arsTableName
              SET ars_success=$1,
                  ars_endtime=now() $statusClause
              WHERE ars_pk = $2",
              $parms, $stmt);
    } else {
      $row = $this->dbManager->getSingleRow(
              "INSERT INTO $arsTableName(agent_fk,upload_fk)
               VALUES ($1,$2) RETURNING ars_pk",
              array($agentId, $uploadId),
              __METHOD__.".update.".$arsTableName);
      if ($row !== false) {
        return $row['ars_pk'];
      }
    }

    return -1;
  }

  public function getCurrentAgentId($agentName, $agentDesc="", $agentRev="")
  {
    $row = $this->dbManager->getSingleRow(
      "SELECT agent_pk FROM agent WHERE agent_name = $1 order by agent_ts desc limit 1",
      array($agentName), __METHOD__."select"
    );

    if ($row === false) {
      $row = $this->dbManager->getSingleRow(
        "INSERT INTO agent(agent_name,agent_desc,agent_rev) VALUES ($1,$2,$3) RETURNING agent_pk",
        array($agentName, $agentDesc, $agentRev), __METHOD__."insert"
      );
      return false !== $row ? intval($row['agent_pk']) : -1;
    }

    return intval($row['agent_pk']);
  }

  /**
   * @brief
   *  The purpose of this function is to return an array of
   *  _ars records for an agent so that the latest agent_pk(s)
   *  can be determined.
   *  This is for _ars tables only, for example, nomos_ars and bucket_ars.
   *  The _ars tables have a standard format but the specific agent ars table
   *  may have additional fields.
   * @param string $tableName - name of the ars table (e.g. nomos_ars)
   * @param int $uploadId
   * @param int $limit - limit number of rows returned.  0=No limit, default=1
   * @param int $agentId - ARS table agent_fk, optional
   *
   * @return mixed
   * assoc array of _ars records.
   *         or FALSE on error, or no rows
   */
  public function agentARSList($tableName, $uploadId, $limit = 1, $agentId = 0, $agentSuccess = true)
  {
    //based on common-agents.php AgentARSList
    if (!$this->dbManager->existsTable($tableName)) {
      return false;
    }

    $arguments = array($uploadId);
    $statementName = __METHOD__ . $tableName;
    $sql = "SELECT * FROM $tableName, agent WHERE agent_pk=agent_fk AND upload_fk=$1 AND agent_enabled";
    if ($agentId) {
      $arguments[] = $agentId;
      $sql .= ' AND agent_fk=$'.count($arguments);
      $statementName .= ".agent";
    }
    if ($agentSuccess) {
      $sql .= " AND ars_success";
      $statementName .= ".suc";
    }
    $sql .= " ORDER BY agent_ts DESC";
    if ($limit > 0) {
      $arguments[] = $limit;
      $sql .= ' limit $'.count($arguments);
      $statementName .= ".lim";
    }
    $this->dbManager->prepare($statementName,$sql);
    $result = $this->dbManager->execute($statementName, $arguments);
    $resultArray = $this->dbManager->fetchAll($result);
    $this->dbManager->freeResult($result);
    return $resultArray;
  }

  /**
   * @brief Returns the list of running or failed agent_pk s. Before latest successful run
   *
   * @param int $uploadId
   * @param $agentName
   * @return int[]  - list of running agent pks
   */
  public function getRunningAgentIds($uploadId, $agentName)
  {
    $arsTableName = $this->getArsTableName($agentName);
    $listOfAllJobs = $this->agentARSList($arsTableName, $uploadId, 0, 0, false);

    $listOfRunningAgents = array();

    if ($listOfAllJobs !== false) {
      foreach ($listOfAllJobs as $job) {
        if ($job ['ars_success'] === $this->dbManager->booleanToDb(true)) {
          continue;
        }
        $listOfRunningAgents[] = intval($job['agent_fk']);
      }
    }
    return $listOfRunningAgents;
  }

  public function getLatestAgentResultForUpload($uploadId, $agentNames)
  {
    $latestScannerProxy = new \Fossology\Lib\Proxy\LatestScannerProxy($uploadId, $agentNames, "latest_scanner$uploadId");

    return $latestScannerProxy->getNameToIdMap();
  }

  /**
   * @param string $agentName
   * @return AgentRef
   */
  public function getCurrentAgentRef($agentName)
  {
    $row = $this->dbManager->getSingleRow("SELECT agent_pk, agent_name, agent_rev from agent WHERE agent_enabled AND agent_name=$1 "
        . "ORDER BY agent_pk DESC LIMIT 1", array($agentName));
    return $this->createAgentRef($row);
  }

  /**
   * @param string $agentName
   * @param int $uploadId
   * @return AgentRef[]
   */
  public function getSuccessfulAgentRuns($agentName, $uploadId)
  {
    $stmt = __METHOD__ . ".getAgent.$agentName";
    $this->dbManager->prepare($stmt,
        $sql = "SELECT agent_pk,agent_rev,agent_name FROM agent LEFT JOIN " . $this->getArsTableName($agentName) . " ON agent_fk=agent_pk "
            . "WHERE agent_name=$2 AND agent_enabled AND upload_fk=$1 AND ars_success "
            . "ORDER BY agent_pk DESC");
    $res = $this->dbManager->execute($stmt, array($uploadId, $agentName));
    $agents = array();
    while ($row = $this->dbManager->fetchArray($res)) {
      $agents[] = $this->createAgentRef($row);
    }
    $this->dbManager->freeResult($res);
    return $agents;
  }

  /**
   * @param string $scannerName
   * @param int $uploadId
   * @return array[] with keys agent_id,agent_rev,agent_name
   */
  public function getSuccessfulAgentEntries($scannerName, $uploadId)
  {
    $stmt = __METHOD__ . ".getAgent.$scannerName";
    $this->dbManager->prepare($stmt,
        $sql = "SELECT agent_pk AS agent_id,agent_rev,agent_name "
            . "FROM agent LEFT JOIN $scannerName" . self::ARS_TABLE_SUFFIX . " ON agent_fk=agent_pk "
            . "WHERE agent_name=$2 AND agent_enabled AND upload_fk=$1 AND ars_success "
            . "ORDER BY agent_id DESC");
    $res = $this->dbManager->execute($stmt, array($uploadId, $scannerName));
    $agents = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    return $agents;
  }

  /**
   * @param string[] $row
   * @return AgentRef
   */
  private function createAgentRef($row)
  {
    return new AgentRef(intval($row['agent_pk']), $row['agent_name'], $row['agent_rev']);
  }

  /**
   * @param $agentName
   * @return string
   */
  private function getArsTableName($agentName)
  {
    return $agentName . self::ARS_TABLE_SUFFIX;
  }

  /**
   * @param string $agentName
   * @return bool
   */
  public function renewCurrentAgent($agentName)
  {
    $this->dbManager->begin();
    $row = $this->dbManager->getSingleRow("SELECT agent_pk, agent_name, agent_rev, agent_desc FROM agent "
        . "WHERE agent_enabled AND agent_name=$1 ORDER BY agent_pk DESC LIMIT 1", array($agentName),__METHOD__.'.get');
    $this->dbManager->getSingleRow("UPDATE agent SET agent_rev=agent_rev||'.'||substr(md5(agent_ts::text),0,6) "
            ."WHERE agent_pk=$1",array($row['agent_pk']),__METHOD__.'.upd');
    unset($row['agent_pk']);
    $this->dbManager->insertTableRow('agent',$row);
    $this->dbManager->commit();
    return true;
  }

  /**
   * @param int $agentId
   * @return string
   */
  public function getAgentName($agentId)
  {
    $row = $this->dbManager->getSingleRow("SELECT agent_name FROM agent WHERE agent_enabled AND agent_pk=$1", array($agentId));
    return ($row===false)?false:$row['agent_name'];
  }

  /**
   * @param int $agentId
   * @return string
   */
  public function getAgentRev($agentId)
  {
    $row = $this->dbManager->getSingleRow("SELECT agent_rev FROM agent WHERE agent_enabled AND agent_pk=$1", array($agentId));
    return ($row===false)?false:$row['agent_rev'];
  }
}
