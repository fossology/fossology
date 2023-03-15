<?php
/***********************************************************
 *
 * The SCANOSS Agent for Fossology tool
 *
 * Copyright (C) 2018-2022 SCANOSS.COM
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 ********************************************************************/
# SPDX-FileCopyrightText: © 2023 SCANOSS.COM

# SPDX-License-Identifier: GPL-2.0-only
use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @dir
 * @brief UI plugin of scanoss Agent
 * @file
 * @brief UI plugin of  scanoss Agent
 * @class ScanossAgentPlugin
 * @brief UI plugin of  scanoss Agent
 */
class ScanossAgentPlugin extends AgentPlugin
{
  public function __construct() {
    $this->Name = "agent_scanoss";
    $this->Title = _("SCANOSS Toolkit");
    $this->AgentName = "scanoss";

    parent::__construct();
  }

/**
   * @brief Render HTML from template
   * @param array $vars Variables using in template
   * @return string HTML rendered from agent_decider.html.twig template
   */
  public function renderContent(&$vars)
  {
    $renderer = $GLOBALS['container']->get('twig.environment');
    return $renderer->load('scanoss.html.twig')->render($vars);
  }



  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, $this->AgentName, "SCANOSS Snippet and License scan", "scanoss_ars");
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::preInstall()
   * @see Fossology::Lib::Plugin::AgentPlugin::preInstall()
   */
  function preInstall()
  {
    $dbManager = $GLOBALS['container']->get('db.manager');
    $latestPkgAgent = $dbManager->getSingleRow("SELECT agent_enabled FROM agent WHERE agent_name=$1 ORDER BY agent_ts LIMIT 1",array('scanoss'));
    if (isset($latestPkgAgent) && !$dbManager->booleanFromDb($latestPkgAgent['agent_enabled']))
    {
      return 0;
    }
    menu_insert("Agents::" . $this->Title, 0, $this->Name);
  }

}

register_plugin(new ScanossAgentPlugin());
