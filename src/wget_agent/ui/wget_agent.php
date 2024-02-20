<?php
/*
 SPDX-FileCopyrightText: © 2012-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file
 * \brief schedule the wget_agent agent
 */

define("TITLE_agent_wget_agent", "wget_agent");

/**
 * @class agent_wget_agent
 * @brief UI plugin for WGET_AGENT
 */
class agent_wget_agent extends FO_Plugin {

  public $Name = "wget_agent";
  public $Title = TITLE_agent_wget_agent;
  public $Version = "1.0";
  public $Dependency = array();
  public $DBaccess = PLUGIN_DB_WRITE;
  public $AgentName = "wget_agent";   // agent.agent_name

  /**
   * @copydoc FO_Plugin::RegisterMenus()
   * @see FO_Plugin::RegisterMenus()
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return (0);
    } // don't run

    /* fake menu item used to identify plugin agents */
    menu_insert("Agents::" . $this->Title, 0, $this->Name);
  }


  /**
   * @brief Check if the upload has results from this agent.
   *
   * @param int $upload_pk Upload to check
   *
   * @returns
   * - 0 = no
   * - 1 = yes, from latest agent version
   * - 2 = yes, from older agent version (does not apply to wget_agent)
   */
  function AgentHasResults($upload_pk)
  {
    return (0);
  } // AgentHasResults()


  /**
   * @brief Queue the wget agent.
   *
   * Before queuing, check if agent needs to be queued. It doesn't need to be queued if:
   *  - It is already queued
   *  - It has already been run by the latest agent version
   *
   * @param int $job_pk
   * @param int $upload_pk
   * @param string $ErrorMsg Error message on failure
   * @param array $Dependencies Array of plugin names representing dependencies.
   *        This is for dependencies that this plugin cannot know about ahead of time.
   *
   * @returns
   * - jq_pk Successfully queued
   * -   0   Not queued, latest version of agent has previously run successfully
   * -  -1   Not queued, error, error string in $ErrorMsg
   */
  function AgentAdd($job_pk, $upload_pk, &$ErrorMsg, $Dependencies)
  {
    $Dependencies[] = "wget_agent";
    return CommonAgentAdd($this, $job_pk, $upload_pk, $ErrorMsg, $Dependencies);
  } // AgentAdd()

};
$NewPlugin = new agent_wget_agent;
