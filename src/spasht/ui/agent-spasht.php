<?php
/*
 SPDX-FileCopyrightText: © 2019 Vivek Kumar <vvksindia@gmail.com>
 Author: Vivek Kumar<vvksindia@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Plugin\AgentPlugin;

include_once(dirname(__DIR__) . "/agent/version.php");

class SpashtAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "spasht";
    $this->Title = _("Spasht Analysis");
    $this->AgentName = "spasht";

    parent::__construct();
  }

  /**
   * Do not register the plugin to UI as spasht requires additional parameters
   * to run
   *
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::preInstall()
   * @see Fossology::Lib::Plugin::AgentPlugin::preInstall()
   */
  function preInstall()
  {
    return false;
  }

  /**
   * Agent can be rescheduled for a new package. No need to check
   * AgentHasResults()
   *
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentAdd()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentAdd()
   */
  public function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies=[],
      $arguments=null, $request=null, $unpackArgs=null)
  {
    $dependencies[] = "agent_adj2nest";

    $jobQueueId = \IsAlreadyScheduled($jobId, $this->AgentName, $uploadId);
    if ($jobQueueId != 0) {
      return $jobQueueId;
    }

    $args = is_array($arguments) ? '' : $arguments;
    return $this->doAgentAdd($jobId, $uploadId, $errorMsg, $dependencies, $uploadId, $args, $request);
  }


  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, $this->AgentName, "spasht agent", "spasht_ars");
  }
}

register_plugin(new SpashtAgentPlugin());
