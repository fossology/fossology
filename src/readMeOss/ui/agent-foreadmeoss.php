<?php
/***********************************************************
 * Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
 * Copyright (C) 2014, Siemens AG
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

/**
 * \file agent-foreadmeoss.php
 * \brief run the decider license agent
 */

define("TITLE_agent_foreadmeoss", _("ReadME_OSS generation"));

class agent_foreadmeoss extends FO_Plugin
{
  public $AgentName;

  function __construct() {
    $this->Name = "agent_foreadmeoss";
    $this->Title = TITLE_agent_foreadmeoss;
    $this->Version = "1.0";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->AgentName = "readmeOssAgent";

    parent::__construct();
  }

  /**
   * \brief  Register additional menus.
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY) return (0);
    //do not advertise this agent: it can be scheduled only from directly
    //menu_insert("Agents::" . $this->Title, 0, $this->Name);

    $text = _("Generate Read me OSS Report");
    menu_insert("Browse-Pfile::Generate  Read me OSS  Report", 0, $this->Name, $text);
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
    return 0; /* this agent can be re run multiple times */
  }

  /**
   * \brief Queue the decider agent.
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
  function AgentAdd($job_pk, $upload_pk, &$ErrorMsg, $Dependencies, $conflictStrategyId=null)
  {
    $Dependencies[] = "agent_adj2nest";

    return CommonAgentAdd($this, $job_pk, $upload_pk, $ErrorMsg, $Dependencies, $upload_pk);
  }
}

$NewPlugin = new agent_foreadmeoss();
