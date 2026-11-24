<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\Reflectory;
use Mockery as M;

require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Test/Reflectory.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Plugin/FO_Plugin.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-plugin.php');
require_once(__DIR__ . '/../../ui/agent-kotoba.php');

/**
 * @class KotobaAgentPluginTest
 * @brief Test for class KotobaAgentPlugin::isAgentScheduledForUpload()
 *
 * Regression coverage for the scancode scheduling-race fix: this method
 * must stay a plain "is it already queued" DB check. A request-intent
 * shortcut was tried and reverted because dependency resolution calls
 * AgentAdd() on whatever it finds here, so treating a not-yet-queued
 * ParmAgent as scheduled queues it with no args instead of waiting for it.
 */
class KotobaAgentPluginTest extends \PHPUnit\Framework\TestCase
{
  /** @var int */
  private $assertCountBefore;
  /** @var KotobaAgentPlugin */
  private $plugin;
  /** @var DbManager|M\MockInterface */
  private $dbManager;

  protected function setUp(): void
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();

    global $container, $Plugins;
    $this->dbManager = M::mock(DbManager::class);
    $container = M::mock('ContainerBuilder');
    $container->shouldReceive('get')->with('db.manager')->andReturn($this->dbManager);

    $Plugins = array();
    $this->plugin = new KotobaAgentPlugin();
  }

  protected function tearDown(): void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    M::close();
  }

  /** Register a minimal stub plugin under $pluginName with the given AgentName. */
  private function registerStubPlugin($pluginName, $agentName)
  {
    global $Plugins;
    $Plugins[$pluginName] = (object) ['AgentName' => $agentName];
  }

  /**
   * @test
   * -# agent_scancode is registered and has an unfinished jobqueue row
   *    for this upload.
   * -# Verify isAgentScheduledForUpload() returns true, and that the DB
   *    query uses the plugin's AgentName ("scancode"), not the plugin
   *    name ("agent_scancode").
   */
  public function testReturnsTrueWhenAgentHasAnUnfinishedJob(): void
  {
    $this->registerStubPlugin('agent_scancode', 'scancode');

    $this->dbManager->shouldReceive('getSingleRow')
      ->once()
      ->with(M::pattern('/jq_endtime IS NULL/'), ['scancode', 42])
      ->andReturn(['jq_pk' => 7]);

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->plugin, 'isAgentScheduledForUpload', [42, 'agent_scancode']
    );

    $this->assertTrue($result);
  }

  /**
   * @test
   * -# agent_scancode is registered but has no unfinished jobqueue row.
   * -# Verify isAgentScheduledForUpload() returns false.
   *    This is the exact state kotoba sees when scancode has not been
   *    queued yet at the point kotoba's dependency check runs.
   */
  public function testReturnsFalseWhenAgentHasNoJob(): void
  {
    $this->registerStubPlugin('agent_scancode', 'scancode');

    $this->dbManager->shouldReceive('getSingleRow')->once()->andReturn(null);

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->plugin, 'isAgentScheduledForUpload', [42, 'agent_scancode']
    );

    $this->assertFalse($result);
  }

  /**
   * @test
   * -# The plugin name does not resolve to any registered plugin.
   * -# Verify isAgentScheduledForUpload() returns false without querying
   *    the DB at all.
   */
  public function testReturnsFalseWithoutDbQueryWhenPluginNotFound(): void
  {
    $this->dbManager->shouldNotReceive('getSingleRow');

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->plugin, 'isAgentScheduledForUpload', [42, 'agent_unknown']
    );

    $this->assertFalse($result);
  }

  /**
   * @test
   * -# Two different scanner agents, each independently checked.
   * -# Verify each lookup uses its own AgentName and upload ID.
   */
  public function testChecksEachAgentIndependently(): void
  {
    $this->registerStubPlugin('agent_nomos', 'nomos');
    $this->registerStubPlugin('agent_scancode', 'scancode');

    $this->dbManager->shouldReceive('getSingleRow')
      ->once()
      ->with(M::any(), ['nomos', 99])
      ->andReturn(['jq_pk' => 1]);
    $this->dbManager->shouldReceive('getSingleRow')
      ->once()
      ->with(M::any(), ['scancode', 99])
      ->andReturn(null);

    $nomosResult = Reflectory::invokeObjectsMethodnameWith(
      $this->plugin, 'isAgentScheduledForUpload', [99, 'agent_nomos']
    );
    $scancodeResult = Reflectory::invokeObjectsMethodnameWith(
      $this->plugin, 'isAgentScheduledForUpload', [99, 'agent_scancode']
    );

    $this->assertTrue($nomosResult);
    $this->assertFalse($scancodeResult);
  }
}
