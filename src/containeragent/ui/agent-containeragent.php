<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @dir
 * @brief UI plugin for the container analysis agent
 * @file
 * @brief UI plugin for the container analysis agent
 * @class ContainerAgentPlugin
 * @brief Registers the Container Analysis agent in the FOSSology Agents menu
 */
class ContainerAgentPlugin extends AgentPlugin
{
  /** @var string $containeragentDesc Description shown in the UI tooltip */
  private $containeragentDesc =
    "Extracts metadata from Docker and OCI container images: " .
    "image name/tag, OS, architecture, entrypoint, environment variables, " .
    "exposed ports, labels, and per-layer history.";

  public function __construct()
  {
    $this->Name      = "agent_containeragent";
    $this->Title     = _("Container Analysis " .
      "<img src=\"images/info_16.png\" " .
      "data-toggle=\"tooltip\" " .
      "title=\"" . $this->containeragentDesc . "\" " .
      "class=\"info-bullet\"/>");
    $this->AgentName = "containeragent";

    parent::__construct();
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   * @see     Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId = 0)
  {
    return CheckARS($uploadId, $this->AgentName,
                    "container image metadata scanner",
                    "containeragent_ars");
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::preInstall()
   * @see     Fossology::Lib::Plugin::AgentPlugin::preInstall()
   */
  function preInstall()
  {
    $dbManager = $GLOBALS['container']->get('db.manager');
    $latest = $dbManager->getSingleRow(
      "SELECT agent_enabled FROM agent " .
      "WHERE agent_name = $1 ORDER BY agent_ts LIMIT 1",
      array('containeragent'));
    if (!empty($latest) && !$dbManager->booleanFromDb($latest['agent_enabled']))
    {
      return 0;
    }
    menu_insert("Agents::" . $this->Title, 0, $this->Name);
  }
}

register_plugin(new ContainerAgentPlugin());
