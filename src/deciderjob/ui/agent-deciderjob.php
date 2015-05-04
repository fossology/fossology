<?php
/***********************************************************
 * Copyright (C) 2014-2015, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

use Fossology\Lib\Plugin\AgentPlugin;

include_once(__DIR__ . "/../agent/version.php");

class DeciderJobAgentPlugin extends AgentPlugin
{
  const CONFLICT_STRATEGY_FLAG = "-k";
    
  function __construct() {
    $this->Name = "agent_deciderjob";
    $this->Title = _("Automatic User License Decider");
    $this->AgentName = AGENT_DECIDER_JOB_NAME;

    parent::__construct();
  }

  /**
   * @overwrite
   */
  function preInstall()
  {
    // no menue entry
  }
  
  /**
   * @overwrite
   * @param int $jobId
   * @param int $uploadId
   * @param string $errorMsg
   * @param array $dependencies
   * @param type $conflictStrategyId
   */
  
  public function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies=array(), $conflictStrategyId=null)
  {
    $dependencies[] = "agent_adj2nest";
 
    $jobQueueId = \IsAlreadyScheduled($jobId, $this->AgentName, $uploadId);
    if ($jobQueueId != 0)
    {
      return $jobQueueId;
    }

    $args = ($conflictStrategyId !== null) ? $this::CONFLICT_STRATEGY_FLAG.$conflictStrategyId : '';

    return $this->doAgentAdd($jobId, $uploadId, $errorMsg, $dependencies, $uploadId, $args);
  }
}

register_plugin(new DeciderJobAgentPlugin());
