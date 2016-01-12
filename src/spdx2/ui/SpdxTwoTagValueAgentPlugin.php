<?php
/*
 Copyright (C) 2015 Siemens AG

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

namespace Fossology\SpdxTwo;

use Fossology\Lib\Plugin\AgentPlugin;

class SpdxTwoTagValueAgentPlugin extends AgentPlugin
{
  public function __construct() {
    $this->Name = "agent_spdx2tv";
    $this->Title =  _("SPDX2 generation in Tag:Value format");
    $this->AgentName = "spdx2tv";
    
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

register_plugin(new SpdxTwoTagValueAgentPlugin());
