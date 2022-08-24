<?php
/*
 SPDX-FileCopyrightText: © 2021 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\CliXml;

use Fossology\Lib\Plugin\AgentPlugin;

class CliXmlAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_clixml";
    $this->Title =  _("CliXml generation");
    $this->AgentName = "clixml";

    parent::__construct();
  }

  function preInstall()
  {
    // no AgentCheckBox
  }

  public function uploadsAdd($uploads)
  {
    if (count($uploads) == 0) {
      return '';
    }
    return '--uploadsAdd='. implode(',', array_keys($uploads));
  }
}

register_plugin(new CliXmlAgentPlugin());
