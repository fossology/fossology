<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief UI plugin for NOMOS
 */

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class NomosAgentPlugin
 * @brief UI plugin for NOMOS
 */
class NomosAgentPlugin extends AgentPlugin
{
  /** @var nomosDesc */
  private $nomosDesc = "Scan files for licenses using regular expressions";

  public function __construct()
  {
    $this->Name = "agent_nomos";
    $this->Title = _(
      "Nomos License Analysis <img src=\"images/info_16.png\" data-toggle=\"tooltip\" title=\"".$this->nomosDesc."\" class=\"info-bullet\"/>");
    $this->AgentName = "nomos";

    parent::__construct();
  }

  /**
   * @copydoc Fossology\Lib\Plugin\AgentPlugin::AgentHasResults()
   * @see \Fossology\Lib\Plugin\AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId = 0)
  {
    return CheckARS($uploadId, $this->AgentName, "license scanner", "nomos_ars");
  }

  /**
   * @copydoc Fossology\Lib\Plugin\AgentPlugin::AgentAdd()
   * @see \Fossology\Lib\Plugin\AgentPlugin::AgentAdd()
   */
  public function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies=[],
      $arguments=null, $request=null, $unpackArgs=null)
  {
    if ($request != null && !is_array($request)) {
      $unpackArgs = intval($request->get('scm', 0)) == 1 ? '-I' : '';
    } else {
      $unpackArgs = intval(@$_POST['scm']) == 1 ? '-I' : '';
    }
    if ($this->AgentHasResults($uploadId) == 1) {
      return 0;
    }

    $jobQueueId = \IsAlreadyScheduled($jobId, $this->AgentName, $uploadId);
    if ($jobQueueId != 0) {
      return $jobQueueId;
    }

    $args = $unpackArgs;
    if (!empty($unpackArgs)) {
      return $this->doAgentAdd($jobId, $uploadId, $errorMsg, array("agent_mimetype"),$uploadId,$args,$request);
    } else {
      return $this->doAgentAdd($jobId, $uploadId, $errorMsg, array("agent_adj2nest"), $uploadId, null, $request);
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

register_plugin(new NomosAgentPlugin());
