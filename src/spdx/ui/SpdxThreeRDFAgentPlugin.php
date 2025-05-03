<?php
/*
 SPDX-FileCopyrightText: © 2024 Abhishek Kumar
 Author: Abhishek Kumar <akumar17871@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Spdx\UI;

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class SpdxThreeRDFAgentPlugin
 * @brief Add multiple uploads to SPDX3 report in RDF format
 */
class SpdxThreeRDFAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_spdx3rdf";
    $this->Title =  _("Export SPDX3.0 RDF report");
    $this->AgentName = "spdx3rdf";

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

register_plugin(new SpdxThreeRDFAgentPlugin());
