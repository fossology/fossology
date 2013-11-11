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
 * \file agent-maintagent.php
 * \brief run the maintenance agent
 */

define("TITLE_agent_maintagent", _("Maintenance agent"));

class agent_maintagent extends FO_Plugin {

  public $Name = "agent_nomos";
  public $Title = TITLE_agent_maintagent;
  public $Version = "1.0";
  public $Dependency = array();
  public $DBaccess = PLUGIN_DB_ADMIN;
  public $AgentName = "maintagent";   // agent.agent_name


  /**
   * \brief  Register additional menus.
   */
  function RegisterMenus() 
  {
    if ($this->State != PLUGIN_STATE_READY)  return (0); // don't run
    menu_insert("Agents::" . $this->Title, 0, $this->Name);
  }


  /**
   * \brief Queue an agent.  This is a simple version of AgentAdd() that can be
   *  used by multiple plugins that only use upload_pk as jqargs.
   *  Before queuing, check if agent needs to be queued.  It doesn't need to be queued if:
   *  - It is already queued
   *  - It has already been run by the latest agent version
   *
   * \param $job_pk
   * \param $upload_pk - not used
   * \param $ErrorMsg - error message on failure
   * \param $Dependencies - array of named dependencies. Each array element is the plugin name.
   *         For example,  array(agent_adj2nest, agent_pkgagent).  
   *         Typically, this will just be array(agent_adj2nest).
   *
   * \returns
   * - jq_pk Successfully queued
   * -   0   Not queued, latest version of agent has previously run successfully
   * -  -1   Not queued, error, error string in $ErrorMsg
   **/
  function AgentAdd($job_pk, $upload_pk, &$ErrorMsg, $Dependencies)
  {
    global $PG_CONN;
    global $Plugins;
    $Deps = array();
    $DependsEmpty = array();

    /* if it is already scheduled, then return success */
    if (($jq_pk = IsAlreadyScheduled($job_pk, $plugin->AgentName)) != 0 ) return $jq_pk;

    /* schedule AgentName */
    $jqargs = "";
    $jq_pk = JobQueueAdd($job_pk, $this->AgentName, $jqargs, "", $Deps);
    if (empty($jq_pk)){
      $ErrorMsg = _("Failed to insert agent $plugin->AgentName into job queue. jqargs: $jqargs");
      return (-1);
    }

    /* Tell the scheduler to check the queue. */
    $success  = fo_communicate_with_scheduler("database", $output, $error_msg);
    if (!$success) $ErrorMsg = $error_msg . "\n" . $output;

    return ($jq_pk);
  } // AgentAdd()

}

$NewPlugin = new agent_maintagent;
?>
