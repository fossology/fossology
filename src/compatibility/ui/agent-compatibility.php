<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Compatibility\Ui;

use Fossology\Lib\Plugin\AgentPlugin;
use Symfony\Component\HttpFoundation\Request;

define("AGENT_COMPATIBILITY_NAME", "compatibility");

class CompatibilityAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_compatibility";
    $this->Title = _("Compatibility License Analysis, scanning for licenses compatibility");
    $this->AgentName = AGENT_COMPATIBILITY_NAME;

    parent::__construct();
  }

  public function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies = [],
                           $arguments = null, $request = null, $unpackArgs = null)
  {
    if ($this->AgentHasResults($uploadId) == 1) {
      return 0;
    }

    $compatibilityDependencies = array("agent_adj2nest");

    if ($request == null) {
      $request = $_POST;
    }
    $compatibilityDependencies = array_merge($compatibilityDependencies,
        $this->getCompatibilityDependencies($request));

    $jobQueueId = \IsAlreadyScheduled($jobId, $this->AgentName, $uploadId);
    if ($jobQueueId != 0) {
      return $jobQueueId;
    }

    return $this->doAgentAdd($jobId, $uploadId, $errorMsg,
        array_unique($compatibilityDependencies), $arguments, null, $request);
  }

  function AgentHasResults($uploadId = 0)
  {
    return CheckARS($uploadId, $this->AgentName, "compatibility agent", "compatibility_ars");
  }

  /**
   * @param Request|array $request
   * @return array
   */
  private function getCompatibilityDependencies($request)
  {
    $dependencies = array();
    if (is_object($request)) {
      $postEmulate = [];
      $postEmulate["Check_agent_nomos"] = intval($request->get(
          "Check_agent_nomos", 0));
      $postEmulate["Check_agent_monk"] = intval($request->get(
          "Check_agent_monk", 0));
      $postEmulate["Check_agent_ojo"] = intval($request->get(
          "Check_agent_ojo", 0));
      $postEmulate["Check_agent_ninka"] = intval($request->get(
          "Check_agent_ninka", 0));
      $request = $postEmulate;
    }
    if (array_key_exists("Check_agent_nomos", $request) && $request["Check_agent_nomos"] == 1) {
      $dependencies[] = "agent_nomos";
    }
    if (array_key_exists("Check_agent_monk", $request) && $request["Check_agent_monk"] == 1) {
      $dependencies[] = "agent_monk";
    }
    if (array_key_exists("Check_agent_ojo", $request) && $request["Check_agent_ojo"] == 1) {
      $dependencies[] = "agent_ojo";
    }
    if (array_key_exists("Check_agent_ninka", $request) && $request["Check_agent_ninka"] == 1) {
      $dependencies[] = "agent_ninka";
    }

    return $dependencies;
  }
}

register_plugin(new CompatibilityAgentPlugin());
