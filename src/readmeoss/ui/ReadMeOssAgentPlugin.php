<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Generate report for multiple uploads at browse mod
 * @dir
 * @brief UI component of ReadMe_OSS agent
 */

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class ReadMeOssAgentPlugin
 * @brief Generate report for multiple uploads at browse mod
 */
class ReadMeOssAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_readmeoss";
    $this->Title =  _("ReadMeOSS generation");
    $this->AgentName = "readmeoss";

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
   * @brief Adds upload ids to the parameter for agent
   * @param int $uploads Array of upload IDs
   * @return string Argument for agent
   */
  public function uploadsAdd($uploads)
  {
    if (count($uploads) == 0) {
      return '';
    }
    return '--uploadsAdd='. implode(',', array_keys($uploads));
  }

  public function uploadsAddWithType($uploads, $type)
  {
    if (count($uploads) == 0) {
      return '';
    }
    return '--uploadsAdd='. implode(',', array_keys($uploads))." --type=".$type;
  }
}

register_plugin(new ReadMeOssAgentPlugin());
