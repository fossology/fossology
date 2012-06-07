<?php
/***********************************************************
 Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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
 * \file wget_agent.php
 * \brief schedule the wget_agent agent
 **/

define("TITLE_agent_wget_agent", "wget_agent");

class agent_wget_agent extends FO_Plugin {

  public $Name = "wget_agent";
  public $Title = TITLE_agent_wget_agent;
  public $Version = "1.0";
  public $Dependency = array();
  public $DBaccess = PLUGIN_DB_ANALYZE;
  public $AgentName = "wget_agent";   // agent.agent_name

  /**
   * \brief Register additional menus.
   **/
  function RegisterMenus() 
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return (0);
    } // don't run

    /* fake menu item used to identify plugin agents */
    menu_insert("Agents::" . $this->Title, 0, $this->Name);
  }


  /**
   * \brief Check if the upload has results from this agent.
   *
   * \param $upload_pk
   *
   * \returns:
   * - 0 = no
   * - 1 = yes, from latest agent version
   * - 2 = yes, from older agent version (does not apply to wget_agent)
   **/
  function AgentHasResults($upload_pk) 
  {
    return (0);
  } // AgentHasResults()


  /**
   * \brief Queue the wget agent.
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
    $Dependencies[] = "wget_agent";
    return CommonAgentAdd($this, $job_pk, $upload_pk, $ErrorMsg, $Dependencies);
  } // AgentAdd()

};
$NewPlugin = new agent_wget_agent;
?>
