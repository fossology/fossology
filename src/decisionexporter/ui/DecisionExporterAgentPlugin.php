<?php
/*
 SPDX-FileCopyrightText: © 2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\DecisionExporter\UI;

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class DecisionExporterAgentPlugin
 * @brief Generate decision dump for uploads
 */
class DecisionExporterAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_decisionexporter";
    $this->Title =  _("FOSSology Decision Exporter");
    $this->AgentName = "decisionexporter";

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

register_plugin(new DecisionExporterAgentPlugin());
