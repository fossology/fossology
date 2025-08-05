<?php
/*
 SPDX-FileCopyrightText: © 2021 Sarita Singh <saritasingh.0425@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Scancode\Ui;

use Fossology\Lib\Plugin\AgentPlugin;
use Symfony\Component\HttpFoundation\Request;

class ScancodesAgentPlugin extends AgentPlugin
{
  const SCAN_FLAG = '-';

  public function __construct()
  {
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

  public function getScriptIncludes(&$vars)
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
   * @param string $errorMsg  Error message which has to be displayed
   * @param Request $request  Session request in html
   * @return int  $jobQueueId jq_pk of scheduled jobqueue or 0 if not scheduled
   */
  public function scheduleAgent($jobId, $uploadId, &$errorMsg, $request)
  {
    $dependencies = array();
    $flags = $request->get('scancodeFlags') ?: array();
    $unpackArgs = intval($request->get('scm', 0)) == 1 ? 'I' : '';
    $parallelParams = $this->getParallelProcessingParams($request);
    $args = $this->getScanCodeArgs($flags, $unpackArgs, $parallelParams);
    if ($args === null) {
      return 0;
    }
    if (!empty($unpackArgs)) {
      $dependencies[] = 'agent_mimetype';
    }
    return parent::AgentAdd($jobId, $uploadId, $errorMsg, array_unique($dependencies), $args);
  }

  /**
   * Get parallel processing parameters from request
   * @param Request $request
   * @return array Parallel processing parameters
   */
  private function getParallelProcessingParams(Request $request)
  {
    global $SysConf;
    $params = array();

    $defaultParallel = isset($SysConf['SYSCONFIG']['ScancodeParallelProcesses']) ?
                       $SysConf['SYSCONFIG']['ScancodeParallelProcesses'] : 4;
    $defaultNice = isset($SysConf['SYSCONFIG']['ScancodeNiceLevel']) ?
                   $SysConf['SYSCONFIG']['ScancodeNiceLevel'] : 15;
    $defaultMaxTasks = isset($SysConf['SYSCONFIG']['ScancodeMaxTasks']) ?
                       $SysConf['SYSCONFIG']['ScancodeMaxTasks'] : 1000;
    $defaultHeartbeat = isset($SysConf['SYSCONFIG']['ScancodeHeartbeatInterval']) ?
                        $SysConf['SYSCONFIG']['ScancodeHeartbeatInterval'] : 60;

    $parallel = intval($request->get('scancode_parallel', $defaultParallel));
    if ($parallel < 1) {
      $parallel = 1;
    }
    if ($parallel > 32) {
      $parallel = 32;
    }
    $params['parallel'] = $parallel;

    $niceLevel = intval($request->get('scancode_nice_level', $defaultNice));
    if ($niceLevel < 0) {
      $niceLevel = 0;
    }
    if ($niceLevel > 19) {
      $niceLevel = 19;
    }
    $params['nice_level'] = $niceLevel;

    $maxTasks = intval($request->get('scancode_max_tasks', $defaultMaxTasks));
    if ($maxTasks < 100) {
      $maxTasks = 100;
    }
    if ($maxTasks > 10000) {
      $maxTasks = 10000;
    }
    $params['max_tasks'] = $maxTasks;

    $heartbeat = intval($request->get('scancode_heartbeat', $defaultHeartbeat));
    $params['heartbeat'] = $heartbeat;

    return $params;
  }

  /**
   * Translate request flags to agent's args string
   * @param string[] $flags Array of flags
   * @param string $unpackArgs Unpack agent args
   * @param array $parallelParams Parallel processing parameters
   * @return null|string NULL if no args created, string otherwise
   */
  public function getScanCodeArgs($flags, $unpackArgs, $parallelParams = array())
  {
    $scanMode = '';
    foreach ($flags as $flag) {
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
    if (empty($scanMode)) {
      return null;
    }
    if (!empty($unpackArgs)) {
      $scanMode .= $unpackArgs;
    }

    $args = self::SCAN_FLAG . $scanMode;

    if (!empty($parallelParams)) {
      if (isset($parallelParams['parallel']) && $parallelParams['parallel'] > 1) {
        $args .= ' --parallel=' . $parallelParams['parallel'];
      }
      if (isset($parallelParams['nice_level'])) {
        $args .= ' --nice-level=' . $parallelParams['nice_level'];
      }
      if (isset($parallelParams['max_tasks'])) {
        $args .= ' --max-tasks=' . $parallelParams['max_tasks'];
      }
      if (isset($parallelParams['heartbeat'])) {
        $args .= ' --heartbeat-interval=' . $parallelParams['heartbeat'];
      }
    }

    return $args;
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

  /**
   * Is scancode-toolkit installed on the system?
   *
   * Checks if scancode executable exists
   */
  public function isScanCodeInstalled()
  {
    global $SysConf;
    return file_exists("/home/" .
      $SysConf['DIRECTORIES']['PROJECTUSER'] . "/pythondeps/bin/scancode");
  }
}

$scanCode = new ScancodesAgentPlugin();
if ($scanCode->isScanCodeInstalled()) {
  register_plugin($scanCode);
}