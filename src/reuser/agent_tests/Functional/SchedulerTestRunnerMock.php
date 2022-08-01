<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Reuser\Test;

use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Db\DbManager;
use Mockery as M;

include_once(__DIR__.'/../../../lib/php/Test/Agent/AgentTestMockHelper.php');
include_once(__DIR__.'/SchedulerTestRunner.php');

include(dirname(dirname(__DIR__)).'/agent/ReuserAgent.php');

/**
 * @class SchedulerTestRunnerMock
 * @brief Create mock objects for reuser
 */
class SchedulerTestRunnerMock implements SchedulerTestRunner
{
  /** @var DbManager $dbManager
   * DB manager object
   */
  private $dbManager;

  /** @var ClearingDao $clearingDao
   * ClearingDao object
   */
  private $clearingDao;
  /** @var CopyrightDao $copyrightDao
   * CopyrightDao object
   */
  private $copyrightDao;
  /** @var ClearingDecisionFilter $clearingDecisionFilter
   * ClearingDecisionFilter object
   */
  private $clearingDecisionFilter;
  /** @var ClearingDecisionProcessor $clearingDecisionProcessor
   * ClearingDecisionProcessor object
   */
  private $clearingDecisionProcessor;
  /** @var UploadDao $uploadDao
   * Upload Dao object
   */
  private $uploadDao;
  /** @var AgentDao $agentDao
   * AgentDao object
   */
  private $agentDao;
  /** @var TreeDao $treeDao
   * TreeDao object
   */
  private $treeDao;

  public function __construct(DbManager $dbManager, AgentDao $agentDao, ClearingDao $clearingDao, UploadDao $uploadDao,
                                                    ClearingDecisionFilter $clearingDecisionFilter, TreeDao $treeDao,
                                                    CopyrightDao $copyrightDao)
  {
    $this->clearingDao = $clearingDao;
    $this->copyrightDao = $copyrightDao;
    $this->uploadDao = $uploadDao;
    $this->agentDao = $agentDao;
    $this->dbManager = $dbManager;
    $this->decisionTypes = new DecisionTypes();
    $this->clearingDecisionFilter = $clearingDecisionFilter;
    $this->treeDao = $treeDao;
  }

  public function run($uploadId, $userId=2, $groupId=2, $jobId=1, $args="")
  {
    $GLOBALS['userId'] = $userId;
    $GLOBALS['jobId'] = $jobId;
    $GLOBALS['groupId'] = $groupId;

    /* these appear not to be used by the reuser: mock them to something wrong
     */
    $this->clearingEventProcessor = M::mock(LicenseRef::class);
    $this->decisionTypes = M::mock(LicenseRef::class);
    $this->agentLicenseEventProcessor = M::mock(LicenseRef::class);

    $container = M::mock('Container');
    $container->shouldReceive('get')->with('db.manager')->andReturn($this->dbManager);
    $container->shouldReceive('get')->with('dao.agent')->andReturn($this->agentDao);
    $container->shouldReceive('get')->with('dao.clearing')->andReturn($this->clearingDao);
    $container->shouldReceive('get')->with('dao.copyright')->andReturn($this->copyrightDao);
    $container->shouldReceive('get')->with('dao.upload')->andReturn($this->uploadDao);
    $container->shouldReceive('get')->with('decision.types')->andReturn($this->decisionTypes);
    $container->shouldReceive('get')->with('businessrules.clearing_event_processor')->andReturn($this->clearingEventProcessor);
    $container->shouldReceive('get')->with('businessrules.clearing_decision_filter')->andReturn($this->clearingDecisionFilter);
    $container->shouldReceive('get')->with('businessrules.clearing_decision_processor')->andReturn($this->clearingDecisionProcessor);
    $container->shouldReceive('get')->with('businessrules.agent_license_event_processor')->andReturn($this->agentLicenseEventProcessor);
    $container->shouldReceive('get')->with('dao.tree')->andReturn($this->treeDao);
    $GLOBALS['container'] = $container;

    $fgetsMock = M::mock(\Fossology\Lib\Agent\FgetsMock::class);
    $fgetsMock->shouldReceive("fgets")->with(STDIN)->andReturn($uploadId, false);
    $GLOBALS['fgetsMock'] = $fgetsMock;

    $exitval = 0;

    ob_start();

    include(dirname(dirname(__DIR__)).'/agent/reuser.php');

    $output = ob_get_clean();

    return array(true, $output, $exitval);
  }
}
