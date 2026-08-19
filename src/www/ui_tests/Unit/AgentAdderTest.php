<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\Reflectory;
use Mockery as M;

require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Test/Reflectory.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Plugin/FO_Plugin.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-plugin.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-menu.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-job.php');

/**
 * @class AgentAdderTest
 * @brief Test for AgentAdder::agentsAdd(), covering the scheduling-order fix
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * Isolated: loading the real AgentAdder class here would collide with
 * ScanOptionsTest's Mockery::mock('overload:\AgentAdder') elsewhere in the suite.
 *
 * Regression coverage for: plain agents (nomos/monk/ojo) must be queued
 * before the ParmAgents loop (decider/kotoba, scancode), because decider
 * (and kotoba, scheduled from it) resolves its scanner dependencies by
 * checking whether they are already queued. Getting this order wrong
 * either drops the dependency silently, or (for args-carrying ParmAgents
 * like scancode) re-schedules the agent with no args before its real
 * scheduleAgent() call runs.
 */
class AgentAdderTest extends \PHPUnit\Framework\TestCase
{
  /** @var int */
  private $assertCountBefore;
  /** @var AgentAdder */
  private $agentAdder;
  /** @var array call order log shared by every fake plugin below */
  private $callLog;

  protected function setUp(): void
  {
    // Loaded per-test, not at file scope: setUpBeforeClass() runs in the
    // parent process, before the isolation above forks a child.
    if (!class_exists('AgentAdder', false)) {
      $GLOBALS['SysConf'] = [];
      $GLOBALS['container'] = new class {
        public function get($name)
        {
          return new \stdClass();
        }
      };
      require_once(__DIR__ . '/../../ui/agent-add.php');
    }

    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();

    global $MenuList, $Plugins;
    $MenuList = array();
    $Plugins = array();
    $this->callLog = array();

    $GLOBALS['SysConf']['auth']['UserId'] = 1;
    $GLOBALS['SysConf']['auth']['GroupId'] = 1;

    // Bypass DefaultPlugin::__construct(): agentsAdd() only touches globals,
    // so the bare object is enough to test it.
    $this->agentAdder = (new \ReflectionClass('AgentAdder'))->newInstanceWithoutConstructor();
  }

  protected function tearDown(): void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    M::close();
  }

  /**
   * Register a fake plugin under $pluginName that logs every AgentAdd()/
   * scheduleAgent() call (with the plugin name) into the shared call log.
   */
  private function registerLoggingPlugin($menu, $pluginName)
  {
    $log = &$this->callLog;
    $plugin = new class($pluginName, $log) {
      public $Name;
      public $State;
      private $log;
      public function __construct($name, &$log)
      {
        $this->Name = $name;
        $this->State = PLUGIN_STATE_READY;
        $this->log = &$log;
      }
      public function AgentAdd(...$args)
      {
        $this->log[] = "$this->Name:AgentAdd";
        return 1;
      }
      public function scheduleAgent(...$args)
      {
        $this->log[] = "$this->Name:scheduleAgent";
        return 1;
      }
    };

    global $Plugins;
    $Plugins[$pluginName] = $plugin;
    menu_insert("$menu::$pluginName", 0, $pluginName);
  }

  private function mockContainer($uploadFilename = 'test.tar')
  {
    $dbManager = M::mock(DbManager::class);
    $dbManager->shouldReceive('getSingleRow')->andReturn(['job_pk' => 1]);

    $upload = M::mock(Upload::class);
    $upload->shouldReceive('getFilename')->andReturn($uploadFilename);
    $uploadDao = M::mock('stdClass');
    $uploadDao->shouldReceive('getUpload')->andReturn($upload);

    $container = M::mock('ContainerBuilder');
    $container->shouldReceive('get')->with('db.manager')->andReturn($dbManager);
    $container->shouldReceive('get')->with('dao.upload')->andReturn($uploadDao);

    $GLOBALS['container'] = $container;
  }

  /**
   * @test
   * -# Select nomos (plain), decider+scancode (ParmAgents), where menu
   *    order puts decider before scancode (the real, alphabetically
   *    sorted case that caused the original bug).
   * -# Verify nomos:AgentAdd runs before decider:scheduleAgent, and
   *    scancode:scheduleAgent also runs before decider:scheduleAgent.
   */
  public function testPlainAgentsAndReorderedParmAgentsRunBeforeDecider(): void
  {
    $this->mockContainer();
    $this->registerLoggingPlugin('Agents', 'agent_nomos');
    $this->registerLoggingPlugin('ParmAgents', 'agent_decider');
    $this->registerLoggingPlugin('ParmAgents', 'agent_scancode');

    $request = new \Symfony\Component\HttpFoundation\Request(
      [], ['agents' => ['agent_nomos', 'agent_decider', 'agent_scancode']]
    );

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->agentAdder, 'agentsAdd', [42, ['agent_nomos', 'agent_decider', 'agent_scancode'], $request]
    );

    $this->assertSame(1, $result);

    $nomosIdx = array_search('agent_nomos:AgentAdd', $this->callLog);
    $scancodeIdx = array_search('agent_scancode:scheduleAgent', $this->callLog);
    $deciderIdx = array_search('agent_decider:scheduleAgent', $this->callLog);

    $this->assertNotFalse($nomosIdx, 'nomos was never scheduled');
    $this->assertNotFalse($scancodeIdx, 'scancode was never scheduled');
    $this->assertNotFalse($deciderIdx, 'decider was never scheduled');
    $this->assertLessThan($deciderIdx, $nomosIdx, 'nomos must be queued before decider');
    $this->assertLessThan($deciderIdx, $scancodeIdx, 'scancode must be queued before decider');
  }

  /**
   * @test
   * -# scancode's real scheduleAgent() call (with correct args, from the
   *    reordered ParmAgents loop) must run before the leftover bare
   *    AgentAdd() pass for any ParmAgent also present in agentsToStart.
   * -# This is the invariant that keeps the pre-existing "picked directly
   *    from the raw agent list" case safe: the bare call becomes a no-op
   *    via IsAlreadyScheduled() only if the real call ran first.
   */
  public function testScancodeScheduleAgentRunsBeforeItsOwnLeftoverAgentAddPass(): void
  {
    $this->mockContainer();
    $this->registerLoggingPlugin('ParmAgents', 'agent_decider');
    $this->registerLoggingPlugin('ParmAgents', 'agent_scancode');

    $request = new \Symfony\Component\HttpFoundation\Request(
      [], ['agents' => ['agent_decider', 'agent_scancode']]
    );

    Reflectory::invokeObjectsMethodnameWith(
      $this->agentAdder, 'agentsAdd', [42, ['agent_decider', 'agent_scancode'], $request]
    );

    $scheduleIdx = array_search('agent_scancode:scheduleAgent', $this->callLog);
    $agentAddIdx = array_search('agent_scancode:AgentAdd', $this->callLog);

    $this->assertNotFalse($scheduleIdx);
    $this->assertNotFalse($agentAddIdx);
    $this->assertLessThan($agentAddIdx, $scheduleIdx);
  }

  /**
   * @test
   * -# agentsToStart is not an array.
   * -# Verify agentsAdd() returns the "bad parameters" error string
   *    instead of proceeding (pre-existing guard, unaffected by the fix).
   */
  public function testRejectsNonArrayAgentsToStart(): void
  {
    $request = new \Symfony\Component\HttpFoundation\Request();

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->agentAdder, 'agentsAdd', [42, 'not-an-array', $request]
    );

    $this->assertSame('bad parameters', $result);
  }
}
