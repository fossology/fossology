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
 * \file agent-fodecider.php
 * \brief run the decider license agent
 */

define("TITLE_agent_fodecider", _("Automatic User License Decider"));

include_once(__DIR__ . "/../agent/version.php");

class agent_fodecider extends FO_Plugin
{
  public $AgentName;

  const CONFLICT_STRATEGY_FLAG = "-k";

  function __construct() {
    $this->Name = "agent_decider";
    $this->Title = TITLE_agent_fodecider;
    $this->Version = "1.0";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->AgentName = AGENT_NAME;

    parent::__construct();
  }

  /**
   * \brief  Register additional menus.
   */
  function RegisterMenus()
  {
    return 0;
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
   * @param int $job_pk
   * @param int $upload_pk
   * @param &string $ErrorMsg - error message on failure
   * @param array $Dependencies - array of plugin names representing dependencies.
   *        This is for dependencies that this plugin cannot know about ahead of time.
   * @param int|null $conflictStrategyId
   * @returns
   * - jq_pk Successfully queued
   * -   0   Not queued, latest version of agent has previously run successfully
   * -  -1   Not queued, error, error string in $ErrorMsg
   **/
  function AgentAdd($job_pk, $upload_pk, &$ErrorMsg, $Dependencies, $conflictStrategyId=null)
  {
    $Dependencies[] = "agent_adj2nest";
    if ($conflictStrategyId !== null)
    {
      $args = $this::CONFLICT_STRATEGY_FLAG . $conflictStrategyId;
    } else
    {
      $args = "";
    }
    return CommonAgentAdd($this, $job_pk, $upload_pk, $ErrorMsg, $Dependencies, $upload_pk, $args);
  }
}

$NewPlugin = new agent_fodecider();
