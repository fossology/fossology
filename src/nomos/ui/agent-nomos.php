<?php
/***********************************************************
 * Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
 * Copyright (C) 2015 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 ***********************************************************/
/**
 * @file
 * @brief UI plugin for NOMOS
 */
use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class NomosAgentPlugin
 * @brief UI plugin for NOMOS
 */
class NomosAgentPlugin extends AgentPlugin
{

  public function __construct()
  {
    $this->Name = "agent_nomos";
    $this->Title = _(
      "Nomos License Analysis, scanning for licenses using regular expressions");
    $this->AgentName = "nomos";

    parent::__construct();
  }

  /**
   * @copydoc Fossology\Lib\Plugin\AgentPlugin::AgentHasResults()
   * @see \Fossology\Lib\Plugin\AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId = 0)
  {
    return CheckARS($uploadId, $this->AgentName, "license scanner", "nomos_ars");
  }
}

register_plugin(new NomosAgentPlugin());
