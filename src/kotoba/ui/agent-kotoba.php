<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Kotoba\UI;

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class KotobaAgentPlugin
 * @brief UI plugin for Kotoba agent
 */
class KotobaAgentPlugin extends AgentPlugin
{
  /** @var string Agent description */
  private $kotobaDesc = "Japanese text analysis agent";

  public function __construct()
  {
    $this->Name = "agent_kotoba";
    $this->Title = _("Kotoba Analysis <img src=\"images/info_16.png\" data-toggle=\"tooltip\" title=\"".$this->kotobaDesc."\" class=\"info-bullet\"/>");
    $this->AgentName = "kotoba";

    parent::__construct();
  }

  /**
   * @copydoc Fossology\Lib\Plugin\AgentPlugin::AgentHasResults()
   * @see \Fossology\Lib\Plugin\AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId = 0)
  {
    return CheckARS($uploadId, $this->AgentName, "kotoba scanner", "kotoba_ars");
  }

  /**
   * @copydoc Fossology\Lib\Plugin\AgentPlugin::AgentAdd()
   * @see \Fossology\Lib\Plugin\AgentPlugin::AgentAdd()
   */
  public function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies=[],
      $arguments=null, $request=null, $unpackArgs=null)
  {
    // Handle SCM flag if needed
    if ($request != null && !is_array($request)) {
      $unpackArgs = intval($request->get('scm', 0)) == 1 ? '-I' : '';
    } else {
      $unpackArgs = intval(@$_POST['scm']) == 1 ? '-I' : '';
    }

    // Check if agent already has results
    if ($this->AgentHasResults($uploadId) == 1) {
      return 0;
    }

    // Check if already scheduled
    $jobQueueId = \IsAlreadyScheduled($jobId, $this->AgentName, $uploadId);
    if ($jobQueueId != 0) {
      return $jobQueueId;
    }

    // Set dependencies and arguments
    $args = $unpackArgs;
    if (!empty($unpackArgs)) {
      return $this->doAgentAdd($jobId, $uploadId, $errorMsg, 
          array("agent_mimetype"), $uploadId, $args, $request);
    } else {
      return $this->doAgentAdd($jobId, $uploadId, $errorMsg, 
          array("agent_adj2nest"), $uploadId, null, $request);
    }
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

register_plugin(new KotobaAgentPlugin());

