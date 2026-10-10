<?php
/*
 SPDX-FileCopyrightText: © 2026 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\ReportAggregator\UI;

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class ReportAggregatorAgentPlugin
 * @brief Register the reportaggregator merge agent
 */
class ReportAggregatorAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_reportaggregator";
    $this->Title = _("Report aggregator merge");
    $this->AgentName = "reportaggregator";

    parent::__construct();
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::preInstall()
   * @see Fossology::Lib::Plugin::AgentPlugin::preInstall()
   */
  function preInstall()
  {
    // Scheduled only via MultiUploadAggregatorScheduler; no AgentCheckBox.
  }
}

register_plugin(new ReportAggregatorAgentPlugin());
