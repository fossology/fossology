<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG


 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Plugin\AgentPlugin;
use Symfony\Component\HttpFoundation\Request;

include_once(__DIR__ . "/../agent/version.php");

/**
 * @class DeciderAgentPlugin
 * @brief UI plugin for DeciderAgent
 */
class DeciderAgentPlugin extends AgentPlugin
{
  const RULES_FLAG = "-r";

  function __construct()
  {
    $this->Name = "agent_decider";
    $this->Title = _("Automatic Concluded License Decider, based on scanners Matches");
    $this->AgentName = AGENT_DECIDER_NAME;

    parent::__construct();
  }


  /**
   * @brief Render HTML from template
   * @param array $vars Variables using in template
   * @return string HTML rendered from agent_decider.html.twig template
   */
  public function renderContent(&$vars)
  {
    global $SysConf;
    $renderer = $GLOBALS['container']->get('twig.environment');
    $vars['isNinkaInstalled'] = false;
    if ($ninkaUi=plugin_find('agent_ninka')) {
      $vars['isNinkaInstalled'] = $ninkaUi->isNinkaInstalled();
    }
    $vars['isSpacyInstalled'] = file_exists("/home/" .
      $SysConf['DIRECTORIES']['PROJECTUSER'] . "/pythondeps/bin/spacy");
    return $renderer->load('agent_decider.html.twig')->render($vars);
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
   * @brief Schedule decider agent
   * @param int $jobId
   * @param int $uploadId
   * @param string $errorMsg
   * @param Request $request Session request
   * @return string
   */
  public function scheduleAgent($jobId, $uploadId, &$errorMsg, $request)
  {
    $dependencies = array();

    $rules = $request->get('deciderRules') ?: array();
    $agents = $request->get('agents') ?: array();
    if (in_array('agent_nomos', $agents)) {
      $checkAgentNomos = true;
    } else {
      $checkAgentNomos = $request->get('Check_agent_nomos') ?: false;
    }

    if (in_array('agent_copyright', $agents)) {
        $checkAgentCopyright = true;
    } else {
      $checkAgentCopyright = $request->get('Check_agent_copyright') ?: false;
    }
    $rulebits = 0;

    foreach ($rules as $rule) {
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
          $dependencies[] = 'agent_nomos';
          $dependencies[] = 'agent_monk';
          $dependencies[] = 'agent_reuser';
          $rulebits |= 0x4;
          break;
        case 'ojoNoContradiction':
          if ($checkAgentNomos) {
            $dependencies[] = 'agent_nomos';
          }
          $dependencies[] = 'agent_ojo';
          $rulebits |= 0x10;
          break;
        case 'wipScannerUpdates':
          $this->addScannerDependencies($dependencies, $request);
          $rulebits |= 0x8;
          break;
        case 'copyrightDeactivation':
          if ($checkAgentCopyright) {
            $dependencies[] = 'agent_copyright';
          }
          $rulebits |= 0x20;
          break;
        case 'copyrightDeactivationClutterRemoval':
          if ($checkAgentCopyright) {
            $dependencies[] = 'agent_copyright';
          }
          $rulebits |= 0x40;
          break;
      }
    }

    if (empty($rulebits)) {
      return 0;
    }

    $args = self::RULES_FLAG.$rulebits;
    return parent::AgentAdd($jobId, $uploadId, $errorMsg, array_unique($dependencies), $args);
  }

  /**
   * @brief Add dependencies on DeciderAgent
   * @param array $dependencies
   * @param Request $request
   */
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
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::preInstall()
   * @see Fossology::Lib::Plugin::AgentPlugin::preInstall()
   */
  public function preInstall()
  {
    menu_insert("ParmAgents::" . $this->Title, 0, $this->Name);
  }
}

register_plugin(new DeciderAgentPlugin());
