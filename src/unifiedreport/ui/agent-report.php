<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class UnifiedReportAgentPlugin
 * @brief Generate unified report for multiple uploads
 */
class UnifiedReportAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_unifiedreport";
    $this->Title =  _("Unified Report Generator");
    $this->AgentName = "unifiedreport";

    parent::__construct();
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::preInstall()
   * @see Fossology::Lib::Plugin::AgentPlugin::preInstall()
   */
  function preInstall()
  {
    // no AgentCheckBox
  }
}

register_plugin(new UnifiedReportAgentPlugin());
