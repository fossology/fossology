<?php
/*
 SPDX-FileCopyrightText: © 2021 Orange
 Author: Piotr Pszczola <piotr.pszczola@orange.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\SpdxTwo\UI;

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class SpdxTwoCommaSeparatedValuesAgentPlugin
 * @brief Add multiple uploads to CSV reports including SPDX identifiers
 */
class SpdxTwoCommaSeparatedValuesAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_spdx2csv";
    $this->Title =  _("Export CSV report (SPDX)");
    $this->AgentName = "spdx2csv";

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

  /**
   * @brief Add uploads to report
   * @param array $uploads Array of upload ids
   * @return string
   */
  public function uploadsAdd($uploads)
  {
    if (count($uploads) == 0) {
      return '';
    }
    return '--uploadsAdd='. implode(',', array_keys($uploads));
  }
}

register_plugin(new SpdxTwoCommaSeparatedValuesAgentPlugin());
