<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\SpdxTwo\UI;

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class SpdxTwoTagValueAgentPlugin
 * @brief Add multiple uploads to SPDX2 report in Tag:Value format
 */
class SpdxTwoTagValueAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_spdx2tv";
    $this->Title =  _("SPDX2 generation in Tag:Value format");
    $this->AgentName = "spdx2tv";

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

register_plugin(new SpdxTwoTagValueAgentPlugin());
