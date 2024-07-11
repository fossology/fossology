<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief UI for SPDX3 agent
 */
namespace Fossology\SpdxThree\UI;

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class SpdxThreeAgentPlugin
 * @brief Generate SPDX3 report for multiple uploads
 */
class SpdxThreeAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_spdx3";
    $this->Title =  _("SPDX3 generation");
    $this->AgentName = "spdx3";

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

register_plugin(new SpdxThreeAgentPlugin());
