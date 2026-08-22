<?php
/*
 SPDX-FileCopyrightText: © 2026 Saksham Mishra

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Minimal AgentPlugin for enhancedreuser (no user-facing UI).
 *
 * This plugin enables the scheduler to discover and schedule the enhancedreuser.
 * The agent is triggered as a dependency of the reuser agent when the user
 * selects "Enhanced Reuse" in the reuser configuration.
 */

namespace Fossology\EnhancedReuser;

use Fossology\Lib\Plugin\AgentPlugin;

include_once(dirname(__DIR__) . "/agent/version.php");

/**
 * @class EnhancedReuserPlugin
 * @brief Agent plugin for enhancedreuser – no UI, only scheduler registration.
 */
class EnhancedReuserPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_enhancedreuser";
    $this->Title = _("Nirjas-based Enhanced Reuse");
    $this->AgentName = "enhancedreuser";

    parent::__construct();
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::preInstall()
   * @see Fossology::Lib::Plugin::AgentPlugin::preInstall()
   */
  public function preInstall()
  {
    // No menu entry – this agent is always scheduled as a reuser dependency.
  }

  /**
   * @brief Schedule the enhancedreuser for the given upload.
   *
   * Also depends on kotoba when it is scheduled for this same upload, since
   * the kotoba license oracle is one of the gates enhancedreuser consults.
   * @param int $jobId
   * @param int $uploadId
   * @param string $errorMsg
   * @param Request $request
   * @return int Job queue id
   */
  public function scheduleAgent($jobId, $uploadId, &$errorMsg, $request)
  {
    $dependencies = array("agent_adj2nest");
    if ($request && $request->get("Check_agent_kotoba", false)) {
      $dependencies[] = "agent_kotoba";
    }
    return $this->doAgentAdd($jobId, $uploadId, $errorMsg,
      $dependencies, $uploadId, null, $request);
  }
}

register_plugin(new EnhancedReuserPlugin());
