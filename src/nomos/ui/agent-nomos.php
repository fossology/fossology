<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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
/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
*************************************************/

/**
 * \file agent-nomos.php
 * \brief run the nomos license agent
 */

define("TITLE_agent_fonomos", _("Nomos License Analysis"));

class agent_fonomos extends FO_Plugin {

  public $Name = "agent_nomos";
  public $Title = TITLE_agent_fonomos;
  // public $MenuList   = "Jobs::Agents::Nomos License Analysis";
  public $Version = "1.0";
  public $Dependency = array();
  public $DBaccess = PLUGIN_DB_ANALYZE;
  public $AgentName = "nomos";   // agent.agent_name


  /**
   * \brief  Register additional menus.
   */
  function RegisterMenus() 
  {
    if ($this->State != PLUGIN_STATE_READY)  return (0); // don't run
    menu_insert("Agents::" . $this->Title, 0, $this->Name);
  }


  /**
   * \brief Check if the upload has already been successfully scanned.
   *
   * \param $upload_pk
   *
   * \returns:
   * - 0 = no
   * - 1 = yes, from latest agent version
   * - 2 = yes, from older agent version 
   **/
  function AgentHasResults($upload_pk)
  {
    return CheckARS($upload_pk, $this->AgentName, "license scanner", "nomos_ars");
  } // AgentHasResults()


  /**
   * \brief Queue the nomos agent.
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
    $Dependencies[] = "agent_adj2nest";
    return CommonAgentAdd($this, $job_pk, $upload_pk, $ErrorMsg, $Dependencies);
  } // AgentAdd()
}

$NewPlugin = new agent_fonomos;
?>
