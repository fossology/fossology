<?php
/***********************************************************
 * Copyright (C) 2015 Siemens AG
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

namespace Fossology\Lib\UI;

use Fossology\Lib\Util\Object;

class MenuHook extends Object
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
    if(is_array($agentList)) {
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

