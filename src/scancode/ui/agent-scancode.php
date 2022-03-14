<?php
/*****************************************************************************
 * SPDX-License-Identifier: GPL-2.0
 * SPDX-FileCopyrightText: 2021 Sarita Singh <saritasingh.0425@gmail.com>
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
 ****************************************************************************/

namespace Fossology\Scancode\Ui;

use Fossology\Lib\Plugin\AgentPlugin;
use Symfony\Component\HttpFoundation\Request;

class ScancodesAgentPlugin extends AgentPlugin
{
  const SCAN_FLAG = '-';

  public function __construct() {
    $this->Name = "agent_scancode";
    $this->Title =  _("Scancode Toolkit");
    $this->AgentName = "scancode";

    parent::__construct();
  }

  /**
   * @brief Render HTML from template
   * @param array $vars Variables using in template
   * @return string HTML rendered from agent_decider.html.twig template
   */
  public function renderContent(&$vars)
  {
    $renderer = $GLOBALS['container']->get('twig.environment');
    return $renderer->load('scancode.html.twig')->render($vars);
  }

  /**
   * @brief Render footer HTML
   * @param array $vars Variables using in template
   * @return string Footer HTML
   */
  public function renderFoot(&$vars)
  {
    return "";
  }

  /**
   * @brief Schedule scancode agent
   * 
   * flags:
   * l-> license,
   * r-> copyright,
   * e-> email,
   * u->url
   * 
   * @param int $jobId  schedule Job Id which has to add
   * @param int $uploadId     Uploaded pfile Id 
   * @param string $errorMsg  Erraor message which has to be dispalyed
   * @param Request $request  Session request in html 
   * @return int  $jobQueueId jq_pk of scheduled jobqueue or 0 if not scheduled 
   */
  public function scheduleAgent($jobId, $uploadId, &$errorMsg, $request)
  {
    $dependencies = array();
    $flags = $request->get('scancodeFlags') ?: array();
    $scanMode = '';
    foreach ($flags as $flag) 
    {
      switch ($flag) 
      {
        case "license":
          $scanMode .= 'l';
          break;
        case "copyright":
          $scanMode .= 'r';
          break;
        case "email":
          $scanMode .= 'e';
          break;
        case "url":
          $scanMode .= 'u';
          break;
      }
    }
    if (empty($scanMode))
    {
      return 0;
    }
    $unpackArgs = intval(@$_POST['scm']) == 1 ? 'I' : '';
    if (!empty($unpackArgs)) 
    {
      $dependencies[] = 'agent_mimetype';
      $scanMode .= $unpackArgs;
    }
    $args = self::SCAN_FLAG.$scanMode;
    return parent::AgentAdd($jobId, $uploadId, $errorMsg, array_unique($dependencies) , $args);
  }
  
  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, $this->AgentName, "scancode agent", "scancode_ars");
  }
  
  /**
   * Check if agent already included in the dependency list
   * @param mixed  $dependencies Array of job dependencies
   * @param string $agentName    Name of the agent to be checked for
   * @return boolean true if agent already in dependency list else false
   */
  protected function isAgentIncluded($dependencies, $agentName)
  {
    foreach ($dependencies as $dependency) {
      if ($dependency == $agentName) {
        return true;
      }
      if (is_array($dependency) && $agentName == $dependency['name']) {
        return true;
      }
    }
    return false;
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::preInstall()
   * @see Fossology::Lib::Plugin::AgentPlugin::preInstall()
   */
  public function preInstall()
  {
    menu_insert("ParmAgents::" . $this->Title, 0, $this->Name);
  }
}

register_plugin(new ScancodesAgentPlugin());
