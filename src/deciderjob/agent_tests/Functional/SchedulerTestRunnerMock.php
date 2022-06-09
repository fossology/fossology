<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\DeciderJob\Test;

use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\BusinessRules\AgentLicenseEventProcessor;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Db\DbManager;
use Mockery as M;

include_once(__DIR__.'/../../../lib/php/Test/Agent/AgentTestMockHelper.php');
include_once(__DIR__.'/SchedulerTestRunner.php');

include_once(dirname(dirname(__DIR__)).'/agent/DeciderJobAgent.php');

/**
 * @class SchedulerTestRunnerMock
 * @brief Mock for scheduler inputs
 */
class SchedulerTestRunnerMock implements SchedulerTestRunner
{
  /** @var DbManager */
  private $dbManager;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var ClearingDecisionProcessor */
  private $clearingDecisionProcessor;
  /** @var AgentLicenseEventProcessor */
  private $agentLicenseEventProcessor;
  /** @var UploadDao */
  private $uploadDao;
  /** @var AgentDao */
  private $agentDao;
  /** @var DecisionTypes */
  private $decisionTypes;
  /** @var HighlightDao */
  private $highlightDao;

  public function __construct(DbManager $dbManager, AgentDao $agentDao, ClearingDao $clearingDao, UploadDao $uploadDao, HighlightDao $highlightDao,
    ClearingDecisionProcessor $clearingDecisionProcessor, AgentLicenseEventProcessor $agentLicenseEventProcessor)
  {
    $this->clearingDao = $clearingDao;
    $this->agentDao = $agentDao;
    $this->uploadDao = $uploadDao;
    $this->highlightDao = $highlightDao;
    $this->dbManager = $dbManager;
    $this->decisionTypes = new DecisionTypes();
    $this->clearingDecisionProcessor = $clearingDecisionProcessor;
    $this->agentLicenseEventProcessor = $agentLicenseEventProcessor;
  }

  /**
   * @brief Mock as agent was called from scheduler
   * @param int $uploadId
   * @param int $userId
   * @param int $groupId
   * @param int $jobId
   * @param string $args
   * @return array Run success code, agent output, agent return code
   */
  public function run($uploadId, $userId=2, $groupId=2, $jobId=1, $args="")
  {
    $GLOBALS['userId'] = $userId;
    $GLOBALS['jobId'] = $jobId;
    $GLOBALS['groupId'] = $groupId;

    $matches = array();

    $opts = array();
    if (preg_match("/-k([0-9]*)/", $args, $matches)) {
      $opts['k'] = $matches[1];
    }

    $GLOBALS['extraOpts'] = $opts;

    $container = M::mock('Container');
    $container->shouldReceive('get')->with('db.manager')->andReturn($this->dbManager);
    $container->shouldReceive('get')->with('dao.agent')->andReturn($this->agentDao);
    $container->shouldReceive('get')->with('dao.clearing')->andReturn($this->clearingDao);
    $container->shouldReceive('get')->with('dao.upload')->andReturn($this->uploadDao);
    $container->shouldReceive('get')->with('dao.highlight')->andReturn($this->highlightDao);
    $container->shouldReceive('get')->with('decision.types')->andReturn($this->decisionTypes);
    $container->shouldReceive('get')->with('businessrules.clearing_decision_processor')->andReturn($this->clearingDecisionProcessor);
    $container->shouldReceive('get')->with('businessrules.agent_license_event_processor')->andReturn($this->agentLicenseEventProcessor);
    $GLOBALS['container'] = $container;

    $fgetsMock = M::mock(\Fossology\Lib\Agent\FgetsMock::class);
    $fgetsMock->shouldReceive("fgets")->with(STDIN)->andReturn($uploadId, false);
    $GLOBALS['fgetsMock'] = $fgetsMock;

    $exitval = 0;

    ob_start();

    include(dirname(dirname(__DIR__)).'/agent/deciderjob.php');

    $output = ob_get_clean();

    return array(true, $output, $exitval);
  }
}
