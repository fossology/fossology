<?php
/***********************************************************
 Copyright (C) 2014-2015, Siemens AG

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
***********************************************************/

use Fossology\Lib\Plugin\AgentPlugin;

class IpAgentPlugin extends AgentPlugin
{
  public function __construct() {
    $this->Name = "agent_ip";
    $this->Title = _("IP Analysis");
    $this->AgentName = "ip";

    parent::__construct();
  }

  function AgentHasResults($upload_pk)
  {
    return CheckARS($upload_pk, $this->AgentName, "ip scanner", "ip_ars");
  }
}

register_plugin(new IpAgentPlugin());
