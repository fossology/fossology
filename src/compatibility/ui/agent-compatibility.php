<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Compatibility\Ui;

use Fossology\Lib\Plugin\AgentPlugin;

class CompatibilityAgentPlugin extends AgentPlugin
{
  public function __construct() {
    $this->Name = "agent_compatibility";
    $this->Title =  _("Compatibility License Analysis, scanning for licenses compatibility");
    $this->AgentName = "compatibility";

    parent::__construct();
  }

  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, $this->AgentName, "compatibility agent", "compatibility_ars");
  }
  
 public function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies=array(), $arguments=null)
  {
    $compatibilityDependencies = array("agent_adj2nest");

    $compatibilityDependencies = array_merge($compatibilityDependencies,
      $this->getCompatibilityDependencies($_POST));

    return $this->doAgentAdd($jobId, $uploadId, $errorMsg,
      array_unique($compatibilityDependencies), $arguments);
  }
  
  private function getCompatibilityDependencies($request)
  {
    $dependencies = array();
    if (array_key_exists("Check_agent_nomos", $request) && $request["Check_agent_nomos"]==1) {
      $dependencies[] = "agent_nomos";
    }
    if (array_key_exists("Check_agent_monk", $request) && $request["Check_agent_monk"]==1) {
      $dependencies[] = "agent_monk";
    }
    if (array_key_exists("Check_agent_ojo", $request) && $request["Check_agent_ojo"]==1) {
      $dependencies[] = "agent_ojo";
    }
    if (array_key_exists("Check_agent_ninka", $request) && $request["Check_agent_ninka"]==1) {
      $dependencies[] = "agent_ninka";
    }
    
    return $dependencies;
  }
  
}
register_plugin(new CompatibilityAgentPlugin());
