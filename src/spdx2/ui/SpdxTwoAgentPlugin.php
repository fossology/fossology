<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief UI for SPDX2 agent
 */
namespace Fossology\SpdxTwo\UI;

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class SpdxTwoAgentPlugin
 * @brief Generate SPDX2 report for multiple uploads
 */
class SpdxTwoAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_spdx2";
    $this->Title =  _("SPDX2 generation");
    $this->AgentName = "spdx2";

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

register_plugin(new SpdxTwoAgentPlugin());
