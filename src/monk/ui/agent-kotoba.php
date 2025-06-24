<?php
/*
SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Plugin\AgentPlugin;

class KotobaAgentPlugin extends AgentPlugin
{
  /** @var string Agent description */
  private $kotobaDesc = "Custom phrase analysis agent";

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

    // Build dependencies list - start with base dependencies
    $baseDependencies = array();
    if (!empty($unpackArgs)) {
      $baseDependencies[] = "agent_mimetype";
    } else {
      $baseDependencies[] = "agent_adj2nest";
    }

    // Add license scanner agents as dependencies if they are scheduled for this upload
    $licenseScannerAgents = array("agent_monk", "agent_nomos", "agent_ojo", "agent_scancode");
    foreach ($licenseScannerAgents as $agentName) {
      // Check if agent is scheduled for this upload (in any job)
      if ($this->isAgentScheduledForUpload($uploadId, $agentName)) {
        $baseDependencies[] = $agentName;
      }
    }

    // Set dependencies and arguments - pass uploadId directly to agent
    $args = $unpackArgs;
    if (!empty($unpackArgs)) {
      $kotobaJqId = $this->doAgentAdd($jobId, $uploadId, $errorMsg,
          $baseDependencies, $uploadId, $args, $request);
    } else {
      $kotobaJqId = $this->doAgentAdd($jobId, $uploadId, $errorMsg,
          $baseDependencies, $uploadId, null, $request);
    }

    // If kotoba was successfully scheduled, schedule deciderjob agent
    if ($kotobaJqId > 0) {
      // Schedule deciderjob agent with kotoba as dependency
      $deciderJobPlugin = \plugin_find("agent_deciderjob");
      if ($deciderJobPlugin !== null) {
        $deciderJobDependencies = array(array('name' => 'agent_kotoba', 'args' => $uploadId));
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

  /**
   * Check if an agent is scheduled for an upload (in any job for that upload)
   * @param int $uploadId The upload ID
   * @param string $agentName The agent name (from agent.agent_name, not plugin name)
   * @return boolean true if agent is scheduled, false otherwise
   */
  private function isAgentScheduledForUpload($uploadId, $pluginName)
  {
    global $PG_CONN;

    // Get the agent name from the plugin
    $plugin = plugin_find($pluginName);
    if ($plugin === null) {
      return false;
    }
    $agentName = $plugin->AgentName;

    // Check if agent is scheduled for this upload in any job
    // Similar to IsAlreadyScheduled but checks across all jobs for the upload
    $sql = "SELECT jq_pk FROM jobqueue, job WHERE job_pk=jq_job_fk " .
           "AND jq_type='$agentName' AND job_upload_fk = $uploadId " .
           "AND jq_endtime IS NULL";
    $result = pg_query($PG_CONN, $sql);
    if ($result === false) {
      return false;
    }
    $isScheduled = (pg_num_rows($result) > 0);
    pg_free_result($result);

    return $isScheduled;
  }
}

register_plugin(new KotobaAgentPlugin());

