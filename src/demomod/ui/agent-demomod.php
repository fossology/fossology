<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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

/**
 * \file
 * \brief run the demomod agent
 */

define("TITLE_AGENT_DEMOMOD", _("Demomod scanner"));

/**
 * @class agent_demomod
 * @brief UI plugin for demomod (handle user requests)
 */
class agent_demomod extends FO_Plugin {

  public $Name = "agent_demomod";       ///< Mod name
  public $Title = TITLE_AGENT_DEMOMOD;  ///< Page title
  public $Version = "1.0";              ///< Plugin versin
  public $Dependency = array();         ///< Dependecy for plugin
  public $DBaccess = PLUGIN_DB_WRITE;   ///< DB access required
  public $AgentName = "demomod";        ///< agent.agent_name


  /**
   * \brief  Register additional menus.
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY) {  return (0); // don't run
    }
    menu_insert("Agents::" . $this->Title, 0, $this->Name);
  }


  /**
   * \brief Check if the upload has already been successfully scanned.
   *
   * \param int $upload_pk
   *
   * \returns:
   * - 0 = no
   * - 1 = yes, from latest agent version
   * - 2 = yes, from older agent version
   **/
  function AgentHasResults($upload_pk)
  {
    return CheckARS($upload_pk, $this->AgentName, "demonstration module scanner", "demomod_ars");
  } // AgentHasResults()


  /**
   * \brief Queue the demomod agent.
   *
   *  Before queuing, check if agent needs to be queued.  It doesn't need to be queued if:
   *  - It is already queued
   *  - It has already been run by the latest agent version
   *
   * \param int $job_pk
   * \param int $upload_pk
   * \param string $ErrorMsg - error message on failure
   * \param array $Dependencies - array of plugin names representing dependencies.
   *        This is for dependencies that this plugin cannot know about ahead of time.
   *
   * \returns
   * - jq_pk Successfully queued
   * -   0   Not queued, latest version of agent has previously run successfully
   * -  -1   Not queued, error, error string in $ErrorMsg
   **/
  function AgentAdd($job_pk, $upload_pk, &$ErrorMsg, $Dependencies)
  {
    $Dependencies[] = "agent_adj2nest";
    return CommonAgentAdd($this, $job_pk, $upload_pk, $ErrorMsg, $Dependencies);
  } // AgentAdd()
}

$NewPlugin = new agent_demomod;

