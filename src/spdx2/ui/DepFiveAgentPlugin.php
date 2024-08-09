<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\SpdxTwo\UI;

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class DepFiveAgentPlugin
 * @brief DEP5 copyright file generation
 */
class DepFiveAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_dep5";
    $this->Title =  _("DEP5 copyright file generation");
    $this->AgentName = "dep5";

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

register_plugin(new DepFiveAgentPlugin());
