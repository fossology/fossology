<?php
/***********************************************************
 * Copyright (C) 2014-2015, Siemens AG
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

use Fossology\Lib\Plugin\AgentPlugin;

include_once(__DIR__ . "/../agent/version.php");

class DeciderAgentPlugin extends AgentPlugin
{
  const RULES_FLAG = "-r";

  function __construct() {
    $this->Name = "agent_decider";
    $this->Title = _("Automatic Concluded License Decider, based on scanners Matches");
    $this->AgentName = AGENT_DECIDER_NAME;

    parent::__construct();
  }


  function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies, $activeRules=array())
  {
    $args = "";
    foreach($activeRules as $rule)
    {
      switch($rule)
      {
        case 'agent_nomos':
          $dependencies[] = 'agent_nomos';
          $args = $args ?: self::RULES_FLAG;
          break;
        case 'agent_monk':
          $dependencies[] = 'agent_monk';
          $args = $args ?: self::RULES_FLAG;
          break;
        default:
          break;
      }
    }

    return parent::AgentAdd($jobId, $uploadId, $errorMsg, $dependencies, $args);
  }
}

register_plugin(new DeciderAgentPlugin());
