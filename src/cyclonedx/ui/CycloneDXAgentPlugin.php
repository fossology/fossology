<?php
/*
 SPDX-FileCopyrightText: © 2023 Sushant Kumar <sushantmishra02102002@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\CycloneDX\UI;

use Fossology\Lib\Plugin\AgentPlugin;

class CycloneDXAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_cyclonedx";
    $this->Title =  _("CycloneDX generation");
    $this->AgentName = "cyclonedx";

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

register_plugin(new CycloneDxAgentPlugin());
