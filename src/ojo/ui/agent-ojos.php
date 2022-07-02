<?php
# SPDX-FileCopyrightText: © 2019 Siemens AG

# SPDX-License-Identifier: GPL-2.0-only

namespace Fossology\Ojo\Ui;

use Fossology\Lib\Plugin\AgentPlugin;

class OjosAgentPlugin extends AgentPlugin
{
  public function __construct() {
    $this->Name = "agent_ojo";
    $this->Title =  _("Ojo License Analysis, scanning for licenses using SPDX-License-Identifier");
    $this->AgentName = "ojo";

    parent::__construct();
  }

  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, $this->AgentName, "ojo agent", "ojo_ars");
  }
  
  /**
   * @copydoc Fossology\Lib\Plugin\AgentPlugin::AgentAdd()
   * @see \Fossology\Lib\Plugin\AgentPlugin::AgentAdd()
   */
  public function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies=array(), $arguments=null)
  {
    $unpackArgs = intval(@$_POST['scm']) == 1 ? '-I' : '';
    if ($this->AgentHasResults($uploadId) == 1) {
      return 0;
    }

    $jobQueueId = \IsAlreadyScheduled($jobId, $this->AgentName, $uploadId);
    if ($jobQueueId != 0) {
      return $jobQueueId;
    }

    $args = $unpackArgs;
    if (!empty($unpackArgs)) {
      return $this->doAgentAdd($jobId, $uploadId, $errorMsg, array("agent_mimetype"),$uploadId,$args);
    } else {
      return $this->doAgentAdd($jobId, $uploadId, $errorMsg, array("agent_adj2nest"), $uploadId);
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
register_plugin(new OjosAgentPlugin());
