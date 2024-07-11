<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\SpdxThree\UI;

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class SpdxThreeJsonAgentPlugin
 * @brief Add multiple uploads to SPDX3 report in Tag:Value format
 */
class SpdxThreeJsonAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_spdx3json";
    $this->Title =  _("SPDX3 generation in JSON format");
    $this->AgentName = "spdx3json";

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

register_plugin(new SpdxThreeJsonAgentPlugin());
