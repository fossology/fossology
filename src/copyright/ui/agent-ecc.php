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

/**
 * @class EccAgentPlugin
 * @brief Create UI plugin for ECC agent
 */
class EccAgentPlugin extends AgentPlugin
{
  public function __construct() {
    $this->Name = "agent_ecc";
    $this->Title = _("ECC Analysis, scanning for text fragments potentially relevant for export control");
    $this->AgentName = "ecc";

    parent::__construct();
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, $this->AgentName, "ecc scanner", "ecc_ars");
  }
}

register_plugin(new EccAgentPlugin());
