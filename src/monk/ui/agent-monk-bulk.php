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
 * \file agent-monk-bulk.php
 * \brief run the monk license agent in bulk clearing mode
 */

define("TITLE_agent_fomonkbulk", _("Monk Bulk License Clearing"));

class agent_fomonkbulk extends FO_Plugin
{
  public $AgentName;

  function __construct() {
    $this->Name = "agent_monk_bulk";
    $this->Title = "TITLE_agent_fomonkbulk";
    $this->Version = "1.0";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->AgentName = "monk";

    parent::__construct();
  }

  /**
   * \brief  Register additional menus.
   */
  function RegisterMenus()
  {
    return 0; /* bulk mode does not need a menu */
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
    return 0; /* bulk mode can always be run */
  }

  /**
   * \brief Queue the monk agent.
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
  function AgentAdd($job_pk, $upload_pk, &$ErrorMsg, $Dependencies, $jq_cmd_args)
  {
    return CommonAgentAdd($this, $job_pk, $upload_pk, $ErrorMsg, $Dependencies, "", $jq_cmd_args);
  }
}

$NewPlugin = new agent_fomonkbulk();
