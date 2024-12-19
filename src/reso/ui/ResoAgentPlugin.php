<?php
/*
 SPDX-FileCopyrightText: © 2021 Orange
 Author: Bartłomiej Dróżdż <bartlomiej.drozdz@orange.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class ResoAgentPlugin
 * @brief Create UI plugin for Reso  agent
 */
class ResoAgentPlugin extends AgentPlugin
{
  /** @var resoDesc */
  private $resoDesc = "REUSE.Software agent marks licensed files with a license found in the .license files (outside of the licensed files), Note: forces *Ojo License Analysis*";

  public function __construct()
  {
    $this->Name = "agent_reso";
    $this->Title =  ("REUSE.Software Analysis <img src=\"images/info_16.png\" data-toggle=\"tooltip\" title=\"".$this->resoDesc."\" class=\"info-bullet\"/>");
    $this->AgentName = "reso";

    parent::__construct();
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, $this->AgentName, "reso agent", "reso_ars");
  }

  /**
   * @copydoc Fossology\Lib\Plugin\AgentPlugin::AgentAdd()
   * @see \Fossology\Lib\Plugin\AgentPlugin::AgentAdd()
   */
  public function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies=[],
      $arguments=null, $request=null, $unpackArgs=null)
  {
    if ($request != null) {
      $copyrightAgentScheduled = intval($request->get("Check_agent_copyright",
              0)) == 1;
    } else {
      $copyrightAgentScheduled = GetParm("Check_agent_copyright",
              PARM_INTEGER) == 1;
    }
    $dependencies[] = "agent_ojo";
    if ($copyrightAgentScheduled) {
      $dependencies[] = "agent_copyright";
    }
    if ($this->AgentHasResults($uploadId) == 1) {
      return 0;
    }

    $jobQueueId = \IsAlreadyScheduled($jobId, $this->AgentName, $uploadId);
    if ($jobQueueId != 0) {
      return $jobQueueId;
    }

    return $this->doAgentAdd($jobId, $uploadId, $errorMsg, $dependencies,
        $uploadId, null, $request);
  }

  /**
   * Check if agent already included in the dependency list
   * @param mixed  $dependencies Array of job dependencies
   * @param string $agentName    Name of the agent to be checked for
   * @return boolean true if agent already in dependency list else false
   */
  protected function isAgentIncluded($dependencies, $agentName)
  {
    foreach ($dependencies as $dependency) {
      if ($dependency == $agentName) {
        return true;
      }
      if (is_array($dependency) && $agentName == $dependency['name']) {
        return true;
      }
    }
    return false;
  }
}

register_plugin(new ResoAgentPlugin());
