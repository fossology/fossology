<?php
/*
 SPDX-FileCopyrightText: © 2024 Abhishek Kumar
 Author: Abhishek Kumar <akumar17871@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Spdx\UI;

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class SpdxThreeTagValueAgentPlugin
 * @brief Add multiple uploads to SPDX3 report in Tag:Value format
 */
class SpdxThreeTagValueAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_spdx3tv";
    $this->Title =  _("SPDX3 generation in Tag:Value format");
    $this->AgentName = "spdx3tv";

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

register_plugin(new SpdxThreeTagValueAgentPlugin());
