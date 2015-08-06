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
use Symfony\Component\HttpFoundation\Request;

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

  
  /**
   * @param array $vars
   * @return string
   */
  public function renderContent(&$vars)
  {
    $renderer = $GLOBALS['container']->get('twig.environment');
    $vars['isNinkaInstalled'] = false;
    if($ninkaUi=plugin_find('agent_ninka'))
    {
      $vars['isNinkaInstalled'] = $ninkaUi->isNinkaInstalled();
    }
    return $renderer->loadTemplate('agent_decider.html.twig')->render($vars);
  }
  
  /**
   * @param array $vars
   * @return string
   */
  public function renderFoot(&$vars)
  {
    return "";
  }

    /**
     * @param int $jobId
     * @param int $uploadId
     * @param string $errorMsg
     * @param Request $request
     * @return string
     */
  public function scheduleAgent($jobId, $uploadId, &$errorMsg, $request)
  {
    $dependencies = array();
   
    $rules = $request->get('deciderRules') ?: array();
    $rulebits = 0;
    
    foreach($rules as $rule)
    {
      switch ($rule) {
        case 'nomosInMonk':
          $dependencies[] = 'agent_nomos';
          $dependencies[] = 'agent_monk';
          $rulebits |= 0x1;
          break;
        case 'nomosMonkNinka':
          $dependencies[] = 'agent_nomos';
          $dependencies[] = 'agent_monk';
          $dependencies[] = 'agent_ninka';
          $rulebits |= 0x2;
          break;
        case 'reuseBulk':
          $dependencies[] = 'agent_reuser';
          $rulebits |= 0x4;
          break;
        case 'wipScannerUpdates':
          $this->addScannerDependencies($dependencies, $request);
          $rulebits |= 0x8;
      }
    }
    
    if (empty($rulebits))
    {
      return 0;
    }

    $args = self::RULES_FLAG.$rulebits;
    return parent::AgentAdd($jobId, $uploadId, $errorMsg, array_unique($dependencies), $args);
  }
  
  protected function addScannerDependencies(&$dependencies, Request $request)
  {
    $agentList = $request->get('agents') ?: array();
    foreach (array('agent_nomos', 'agent_monk', 'agent_ninka') as $agentName) {
      if (in_array($agentName, $dependencies)) {
        continue;
      }
      if ($request->get('Check_'.$agentName)) {
        $dependencies[] = $agentName;
        continue;
      }
      if (in_array($agentName, $agentList)) {
        $dependencies[] = $agentName;
      }
    }
  }
  
  /**
   * @override
   */
  public function preInstall()
  {
    menu_insert("ParmAgents::" . $this->Title, 0, $this->Name);
  }

}

register_plugin(new DeciderAgentPlugin());
