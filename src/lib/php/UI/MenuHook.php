<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\UI;

class MenuHook
{
  /**
   * @param string $hook 'ParmAgents'|'Agents'|'UploadMulti'
   * @return array
   */
  public static function getAgentPluginNames($hook='Agents')
  {
    $maxDepth = 0;
    $agentList = menu_find($hook, $maxDepth) ?: array();
    $agentPluginNames = array();
    if (is_array($agentList)) {
      foreach ($agentList as $parmAgent) {
        $agent = plugin_find_id($parmAgent->URI);
        if (!empty($agent) && $agent !== -1 &&
            !in_array($agent, $agentPluginNames)) {
          $agentPluginNames[] = $agent;
        }
      }
    }
    return $agentPluginNames;
  }

  /**
   * Schedule agents that carry their own jq_cmd_args before decider.
   * Decider (and kotoba, scheduled from it) resolves dependencies by calling
   * AgentAdd() on them, which queues a not-yet-scheduled agent with default
   * args and then blocks its real scheduleAgent() via IsAlreadyScheduled().
   * @param[in,out] array $parmList List of parameterized agent plugin names
   */
  public static function rearrangeParmAgentsBeforeDecider(&$parmList)
  {
    $deciderKey = array_search('agent_decider', $parmList);
    if ($deciderKey === false) {
      return;
    }

    $early = array();
    foreach (array('agent_reuser', 'agent_scancode') as $name) {
      $key = array_search($name, $parmList);
      if ($key !== false && $key > $deciderKey) {
        $early[] = $name;
        unset($parmList[$key]);
      }
    }
    if (empty($early)) {
      return;
    }

    $parmList = array_values($parmList);
    array_splice($parmList, array_search('agent_decider', $parmList), 0, $early);
  }
}
