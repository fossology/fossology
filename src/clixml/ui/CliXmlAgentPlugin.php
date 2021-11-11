<?php
/*
 Copyright (C) 2021 Siemens AG

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

namespace Fossology\CliXml;

use Fossology\Lib\Plugin\AgentPlugin;

class CliXmlAgentPlugin extends AgentPlugin
{
  public function __construct() {
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
