<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @namespace Fossology::DeciderJob::UI
 * @brief DeciderJobAgent's UI
 */
namespace Fossology\DeciderJob\UI;

use Fossology\Lib\Plugin\AgentPlugin;

include_once(__DIR__ . "/../agent/version.php");

/**
 * @class DeciderJobAgentPlugin
 * @brief UI plugin for DeciderJobAgent
 */
class DeciderJobAgentPlugin extends AgentPlugin
{
  const CONFLICT_STRATEGY_FLAG = "-k";

  function __construct()
  {
    $this->Name = "agent_deciderjob";
    $this->Title = _("Automatic User License Decider");
    $this->AgentName = AGENT_DECIDER_JOB_NAME;

    parent::__construct();
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::preInstall()
   * @see Fossology::Lib::Plugin::AgentPlugin::preInstall()
   */
  function preInstall()
  {
    // no menu entry
  }

  /**
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

    $args = ($arguments !== null) ? $this::CONFLICT_STRATEGY_FLAG.$arguments : '';

    return $this->doAgentAdd($jobId, $uploadId, $errorMsg, $dependencies,
        $uploadId, $args, $request);
  }
}

register_plugin(new DeciderJobAgentPlugin());
