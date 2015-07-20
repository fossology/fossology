<?php
/***********************************************************
 * Copyright (C) 2014-2015, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

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