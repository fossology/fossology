<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief UI components for ununpack agent
 * @file
 * @brief UI plugin for ununpack agent
 */

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class UnpackAgentPlugin
 * @brief UI for ununpack agent to schedule a job
 */
class UnpackAgentPlugin extends AgentPlugin
{
  public function __construct() {
    $this->Name = "agent_unpack";
    $this->Title = _("Schedule an Unpack");
    $this->AgentName = "ununpack";

    parent::__construct();
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, "ununpack", "Archive unpacker", "ununpack_ars");
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentAdd()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentAdd()
   */
  public function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies=[],
      $arguments=null, $request=null, $unpackArgs=null)
  {

    $jobQueueId = \IsAlreadyScheduled($jobId, $this->AgentName, $uploadId);
    if ($jobQueueId != 0)
    {
       return $jobQueueId;
    }

    return $this->doAgentAdd($jobId, $uploadId, $errorMsg, $dependencies, $uploadId, $arguments, $request);
  }
}

register_plugin(new UnpackAgentPlugin());
