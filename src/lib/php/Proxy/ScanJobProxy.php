<?php
/*
Copyright (C) 2014, Siemens AG

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

namespace Fossology\Lib\Proxy;

use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Util\Object;
use Fossology\Lib\Dao\AgentDao;

/**
 * Class AgentDao
 * @package Fossology\Lib\Dao
 */
class ScanJobProxy extends Object
{
  const ARS_TABLE_SUFFIX = "_ars";

  /** @var AgentDao */
  private $agentDao;
  /** @var int */
  private $uploadId;
  /** @var AgentRef[][] */
  private $successfulScanners = array();
  /** @var int[] */
  private $latestSuccessfulAgentIds = array();

  /**
   * @param AgentDao $agentDao
   * @param int $uploadId
   */
  function __construct(AgentDao $agentDao, $uploadId)
  {
  //  $this->dbManager = $dbManager;
    $this->agentDao = $agentDao;
    $this->uploadId = $uploadId;
  }
  
  public function getSuccessfulAgents()
  {
    $successfulAgents = array();
    foreach ($this->successfulScanners as $scanAgents)
    {
      $successfulAgents = array_merge($successfulAgents, $scanAgents);
    }
    return $successfulAgents;
  }
  
  public function getLatestSuccessfulAgentIds()
  {
    $agentIds = array();
    foreach ($this->successfulScanners as $agentName=>$scanAgents)
    {
      $agentRef = $scanAgents[0];
      $agentIds[$agentName] = $agentRef->getAgentId();
    }
    return $agentIds;
  }
  
  public function getLatestSucessfulAgentRefs()
  {
    $agentRefs = array();
    foreach ($this->successfulScanners as $agentName=>$scanAgents)
    {
      $agentRefs[$agentName] = $scanAgents[0];
    }
    return $agentRefs;
  }
  
  public function createAgentStatus($scannerAgents)
  {
    $scannerVars = array();
    foreach ($scannerAgents as $agentName)
    {
      $agentHasArsTable = $this->agentDao->arsTableExists($agentName);
      if (empty($agentHasArsTable))
      {
        continue;
      }
      $scannerVars[] = $this->scanAgentStatus($agentName);
    }
    return $scannerVars;
  }

  public function getAgentMap()
  {        
    $agentMap = array();
    foreach ($this->getSuccessfulAgents() as $agent)
    {
      $agentMap[$agent->getAgentId()] = $agent->getAgentName() . " " . $agent->getAgentRevision();
    }
    return $agentMap;
  }
  
  /**
   * @brief get status var and store successfulAgents
   * @param string $agentName
   * @return mixed[]
   */
  protected function scanAgentStatus($agentName)
  {
    $successfulAgents = $this->agentDao->getSuccessfulAgentEntries($agentName,$this->uploadId);
    $vars['successfulAgents'] = $successfulAgents;
    $vars['uploadId'] = $this->uploadId;
    $vars['agentName'] = $agentName;
   
    if (!count($successfulAgents))
    {
      $vars['isAgentRunning'] = count($this->agentDao->getRunningAgentIds($this->uploadId, $agentName)) > 0;
      return $vars;
    }  
    
    $latestSuccessfulAgent = $successfulAgents[0];
    $currentAgentRef = $this->agentDao->getCurrentAgentRef($agentName);
    $vars['currentAgentId'] = $currentAgentRef->getAgentId();
    $vars['currentAgentRev'] = $currentAgentRef->getAgentRevision();
    if ($currentAgentRef->getAgentId() != $latestSuccessfulAgent['agent_id'])
    {
      $runningJobs = $this->agentDao->getRunningAgentIds($this->uploadId, $agentName);
      $vars['isAgentRunning'] = in_array($currentAgentRef->getAgentId(), $runningJobs);
    }

    foreach ($successfulAgents as $agent)
    {
      $this->successfulScanners[$agentName][] = new AgentRef($agent['agent_id'], $agentName, $agent['agent_rev']);
    }
    return $vars;
  }
  
} 