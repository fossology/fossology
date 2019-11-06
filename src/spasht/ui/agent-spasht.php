<?php
/***********************************************************
 * Copyright (C) 2019
 * Author: Vivek Kumar<vvksindia@gmail.com>
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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\AgentPlugin;

class SpashtAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_spasht";
    $this->Title = _("Spasht Analysis");
    $this->AgentName = "spasht";

    parent::__construct();
  }

  /**
   * Register the plugin to UI
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::preInstall()
   * @see Fossology::Lib::Plugin::AgentPlugin::preInstall()
   */
  function preInstall()
  {
      menu_insert("Agents::" . $this->Title, 0, $this->Name);
  }


  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, $this->AgentName, "spasht scanner", "spasht_ars");
  }
}

register_plugin(new SpashtAgentPlugin());
