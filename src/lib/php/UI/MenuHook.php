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
        if (!empty($agent)) {
          $agentPluginNames[] = $agent;
        }
      }
    }
    return $agentPluginNames;
  }
}
