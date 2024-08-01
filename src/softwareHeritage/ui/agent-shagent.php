<?php
/*
 SPDX-FileCopyrightText: © 2019 Sandip Kumar Bhuyan <sandipbhuyan@gmail.com>
 Author: Sandip Kumar Bhuyan<sandipbhyan@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class SoftwareHeritageAgentPlugin
 * @brief Create UI plugin for Software Heritage agent
 */
class softwareHeritageAgentPlugin extends AgentPlugin
{
  /** @var shAgentDesc */
  private $shAgentDesc = "Checks if the current file (or files in an upload) has been published in Software Heritage or if it is genuinely part of the distribution.";

  public function __construct()
  {
    $this->Name = "agent_shagent";
    $this->Title =  ("Software Heritage Analysis <img src=\"images/info_16.png\" data-toggle=\"tooltip\" title=\"".$this->shAgentDesc."\" class=\"info-bullet\"/>");
    $this->AgentName = "softwareHeritage";

    parent::__construct();
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, $this->AgentName, "Software Heritage scanner", "softwareHeritage");
  }
}

register_plugin(new softwareHeritageAgentPlugin());
