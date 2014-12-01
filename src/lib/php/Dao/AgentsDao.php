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

use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;
use Monolog\Logger;

/**
 * Class AgentsDao
 * @package Fossology\Lib\Dao
 */
class AgentsDao extends Object
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

  public function arsTableExists($agentName) {
    return $this->dbManager->existsTable($this->getArsTableName($agentName));
  }

  /**
   * @brief
   *  The purpose of this function is to return an array of
   *  _ars records for an agent so that the latest agent_pk(s)
   *  can be determined.
   *  This is for _ars tables only, for example, nomos_ars and bucket_ars.
   *  The _ars tables have a standard format but the specific agent ars table
   *  may have additional fields.
   * @todo make this function private
   * @param string $tableName - name of the ars table (e.g. nomos_ars)
   * @param int $uploadId
   * @param int $limit - limit number of rows returned.  0=No limit, default=1
   * @param int $agentId - ARS table agent_fk, optional
   *
   * @return mixed
   * assoc array of _ars records.
   *         or FALSE on error, or no rows
   */
  public function agentARSList($tableName, $uploadId, $limit = 1, $agentId = 0, $agentSuccess = TRUE)
  {
    //based on common-agents.php AgentARSList
    if (!$this->dbManager->existsTable($tableName))
    {
      return false;
    }

    $arguments = array($uploadId);
    $statementName = __METHOD__ . $tableName;
    $sql = "SELECT * FROM $tableName, agent WHERE agent_pk=agent_fk AND upload_fk=$1 AND agent_enabled";
    if ($agentId)
    {
      $arguments[] = $agentId;
      $sql .= ' AND agent_fk=$'.count($arguments);
      $statementName .= ".agent";
    }
    if ($agentSuccess)
    {
      $sql .= " AND ars_success";
      $statementName .= ".suc";
    }
    $sql .= " ORDER BY agent_ts DESC";
    if ($limit > 0)
    {
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
    $listOfAllJobs = $this->agentARSList($arsTableName, $uploadId, 0, 0, FALSE);

    $listOfRunningAgents = array();

    if ($listOfAllJobs !== false)
    {
      foreach ($listOfAllJobs as $job)
      {
        if ($job ['ars_success'] === $this->dbManager->booleanToDb(true) )
        {
          continue;
        }
        $listOfRunningAgents[] = intval($job['agent_fk']);
      }
    }
    return $listOfRunningAgents;
  }

  public function getLatestAgentResultForUpload($uploadId, $agentNames)
  {
    $agentLatestMap = array();
    foreach ($agentNames as $agentName)
    {
      $sql = "
SELECT
  agent_pk,
  ars_success,
  agent_name
FROM " . $this->getArsTableName($agentName) . " ARS
INNER JOIN agent A ON ARS.agent_fk = A.agent_pk
WHERE upload_fk=$1
  AND A.agent_name = $2
ORDER BY agent_fk DESC";

      $statementName = __METHOD__ . ".$agentName";
      $this->dbManager->prepare($statementName, $sql);
      $res = $this->dbManager->execute($statementName, array($uploadId, $agentName));

      while ($row = $this->dbManager->fetchArray($res))
      {
        if ($this->dbManager->booleanFromDb($row['ars_success']))
        {
          $agentLatestMap[$agentName] = intval($row['agent_pk']);
          break;
        }
      }
      $this->dbManager->freeResult($res);
    }
    return $agentLatestMap;
  }
    
  /**
   * @param string $agentName
   * @return AgentRef
   */
  public function getCurrentAgent($agentName)
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
    while ($row = $this->dbManager->fetchArray($res))
    {
      $agents[] = $this->createAgentRef($row);
    }
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
} 