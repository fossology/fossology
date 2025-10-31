<?php
/*
SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Plugin\AgentPlugin;

class KotobaBulkAgentPlugin extends AgentPlugin
{
  /** @var string Agent description */
  private $kotobaDesc = "Custom phrase analysis agent";

  public function __construct()
  {
    $this->Name = "agent_kotoba_bulk";
    $this->Title = _("Kotoba Analysis <img src=\"images/info_16.png\" data-toggle=\"tooltip\" title=\"".$this->kotobaDesc."\" class=\"info-bullet\"/>");
    $this->AgentName = "kotobabulk";

    parent::__construct();
  }

  /**
   * @copydoc Fossology\Lib\Plugin\AgentPlugin::AgentHasResults()
   * @see \Fossology\Lib\Plugin\AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId = 0)
  {
    return CheckARS($uploadId, $this->AgentName, "kotoba scanner", "kotobabulk_ars");
  }

  function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies=[],
     $arguments=null, $request=null, $unpackArgs=null)
  {
    // Handle SCM flag if needed
    if ($request != null && !is_array($request)) {
      $unpackArgs = intval($request->get('scm', 0)) == 1 ? '-I' : '';
    } else {
      $unpackArgs = intval(@$_POST['scm']) == 1 ? '-I' : '';
    }

    // Check if agent already has results for this upload
    if ($this->AgentHasResults($uploadId) == 1) {
      return 0;
    }

    // Check if already scheduled
    $jobQueueId = \IsAlreadyScheduled($jobId, $this->AgentName, $uploadId);
    if ($jobQueueId != 0) {
      return $jobQueueId;
    }

    // Check if there are any active custom phrases
    if (!$this->hasActiveCustomPhrases()) {
      $errorMsg = _("No active custom phrases found. Please add and activate custom phrases before running the kotoba bulk agent.");
      return -1;
    }

    // Set dependencies and arguments - pass uploadId directly to agent
    $args = $unpackArgs;
    if (!empty($unpackArgs)) {
      $kotobaJqId = $this->doAgentAdd($jobId, $uploadId, $errorMsg, 
          array("agent_mimetype"), $uploadId, $args, $request);
    } else {
      $kotobaJqId = $this->doAgentAdd($jobId, $uploadId, $errorMsg, 
          array("agent_adj2nest"), $uploadId, null, $request);
    }

    // If kotobabulk was successfully scheduled, schedule deciderjob agent
    if ($kotobaJqId > 0) {
      // Schedule deciderjob agent with kotobabulk as dependency
      // (decider agent is left for users to schedule manually via UI if needed)
      $deciderJobPlugin = \plugin_find("agent_deciderjob");
      if ($deciderJobPlugin !== null) {
        $deciderJobDependencies = array(array('name' => 'agent_kotoba_bulk', 'args' => $uploadId));
        $deciderJobErrorMsg = '';
        $deciderJobJqId = $deciderJobPlugin->AgentAdd($jobId, $uploadId, $deciderJobErrorMsg, $deciderJobDependencies, null, $request);
        
        if (!empty($deciderJobErrorMsg)) {
          $errorMsg .= " DeciderJob scheduling: " . $deciderJobErrorMsg;
        }
        
        // Return deciderjob queue ID as the final job in the chain
        if ($deciderJobJqId > 0) {
          return $deciderJobJqId;
        }
      }
    }

    return $kotobaJqId;
  }

  /**
   * Check if there are any active custom phrases in the database
   * @return boolean true if there are active phrases, false otherwise
   */
  private function hasActiveCustomPhrases()
  {
    global $container;
    /** @var DbManager $dbManager */
    $dbManager = $container->get('db.manager');
    
    $sql = "SELECT COUNT(*) as count FROM custom_phrase WHERE is_active = true";
    $result = $dbManager->getSingleRow($sql);
    
    return ($result['count'] > 0);
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

register_plugin(new KotobaBulkAgentPlugin());
