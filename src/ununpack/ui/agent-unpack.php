<?php
/***********************************************************
 Copyright (C) 2008-2012 Hewlett-Packard Development Company, L.P.

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
 * \file agent_unpack.php
 * \brief the unpack ui, and add unpack job, joqueue
 */

define("TITLE_agent_unpack", _("Schedule an Unpack"));

class agent_unpack extends FO_Plugin
{
  public $Name       = "agent_unpack";
  public $Title      = TITLE_agent_unpack;
  // public $MenuList   = "Jobs::Agents::Unpack";
  public $Version    = "1.0";
  public $Dependency = array();
  public $DBaccess   = PLUGIN_DB_UPLOAD;
  public $AgentName = "ununpack";   // agent.agent_name


  /**
   * \brief register additional menus.
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return(0);
    } // don't run
    menu_insert("Agents::" . $this->Title,0,$this->Name);
  }

  /**
   * \brief Check if the upload has already been successfully unpacked.
   *
   * \param $upload_pk
   *
   * \returns:
   * - 0 = no
   * - 1 = yes, from latest agent version
   * - 2 = yes, from older agent version (does not apply to adj2nest)
   **/
  function AgentHasResults($upload_pk)
  {
    return CheckARS($upload_pk, "unpack", "Archive unpacker", "ununpack_ars");
  } // AgentHasResults()


  /**
   * \brief Queue the unpack and adj2nest agents.
   *  Before queuing, check if agent needs to be queued.  It doesn't need to be queued if:
   *  - It is already queued
   *  - It has already been run by the latest agent version
   *
   * \param $job_pk
   * \param $upload_pk
   * \param $ErrorMsg - error message on failure
   * \param $Dependencies - array of plugin names representing dependencies.
   *        This is for dependencies that this plugin cannot know about ahead of time.
   *
   * \returns
   * - jq_pk Successfully queued
   * -   0   Not queued, latest version of agent has previously run successfully
   * -  -1   Not queued, error, error string in $ErrorMsg
   **/
  function AgentAdd($job_pk, $upload_pk, &$ErrorMsg, $Dependencies)
  {
    return CommonAgentAdd($this, $job_pk, $upload_pk, $ErrorMsg, $Dependencies);
  } // AgentAdd()

};
$NewPlugin = new agent_unpack;
?>
