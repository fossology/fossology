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

define("TITLE_agent_fodecider", _("Automatic Concluded License Decider, based on scanners Matches"));

include_once(__DIR__ . "/../agent/version.php");

class agent_fodecider extends FO_Plugin
{
  public $AgentName;

  const RULES_FLAG = "-r";

  function __construct() {
    $this->Name = "agent_decider";
    $this->Title = TITLE_agent_fodecider;
    $this->Version = "1.0";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->AgentName = AGENT_DECIDER_NAME;

    parent::__construct();
  }

  /**
   * \brief  Register additional menus.
   */
  function RegisterMenus()
  {
    if ($this->State == PLUGIN_STATE_READY) {
      menu_insert("Agents::" . $this->Title, 0, $this->Name);
    }
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
   * @param int $jobId
   * @param int $uploadId
   * @param &string $errorMsg - error message on failure
   * @param array $dependencies - array of plugin names representing dependencies.
   *        This is for dependencies that this plugin cannot know about ahead of time.
   * @param int|null $activeRules
   * @returns
   * - jq_pk Successfully queued
   * -   0   Not queued, latest version of agent has previously run successfully
   * -  -1   Not queued, error, error string in $ErrorMsg
   **/
  function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies, $activeRules=1)
  {
    $dependencies[] = "agent_adj2nest";
    if ($activeRules !== null)
    {
      $args = self::RULES_FLAG . $activeRules;
      $dependencies[] = 'agent_nomos';
      $dependencies[] = 'agent_monk';
    }
    else
    {
      $args = "";
    }
    return CommonAgentAdd($this, $jobId, $uploadId, $errorMsg, $dependencies, $uploadId, $args);
  }
}

$NewPlugin = new agent_fodecider();
