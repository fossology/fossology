<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Ninka\Ui;

use Fossology\Lib\Plugin\AgentPlugin;

class NinkaAgentPlugin extends AgentPlugin
{
  public function __construct() {
    $this->Name = "agent_ninka";
    $this->Title =  _("Ninka License Analysis");
    $this->AgentName = "ninka";

    parent::__construct();
  }

  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, $this->AgentName, "ninka agent", "ninka_ars");
  }
  
  function preInstall()
  {
    if ($this->isNinkaInstalled()) {
      menu_insert("Agents::" . $this->Title, 0, $this->Name);
    }
  }
  
  public function isNinkaInstalled()
  {
    exec('which ninka', $lines, $returnVar);
    return (0==$returnVar);
  }
}

register_plugin(new NinkaAgentPlugin());