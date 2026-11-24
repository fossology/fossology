<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Test\Reflectory;
use Symfony\Component\HttpFoundation\Request;

require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Test/Reflectory.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Plugin/FO_Plugin.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-plugin.php');
require_once(__DIR__ . '/../../ui/DeciderAgentPlugin.php');

/**
 * @class DeciderAgentPluginTest
 * @brief Test for class DeciderAgentPlugin, covering the malformed-request
 *        guards on scheduleAgent(), addScannerDependencies() and
 *        getLicenseTypeConf() (deciderRules/agents/licenseTypeConc arriving
 *        as a scalar instead of an array throws a TypeError before the fix).
 */
class DeciderAgentPluginTest extends \PHPUnit\Framework\TestCase
{
  /** @var DeciderAgentPlugin */
  private $plugin;

  protected function setUp(): void
  {
    $this->plugin = new DeciderAgentPlugin();
  }

  // Only cases where rulebits stays empty are exercised: those return 0
  // before scheduleAgent() reaches parent::AgentAdd(), which needs a DB.

  /**
   * @test
   * -# deciderRules arrives as a scalar string instead of an array
   *    (e.g. ?deciderRules=x on the query string).
   * -# Verify scheduleAgent() returns 0 instead of throwing.
   */
  public function testScheduleAgentToleratesScalarDeciderRules(): void
  {
    $request = new Request(['deciderRules' => 'not-an-array']);
    $errorMsg = '';

    $result = $this->plugin->scheduleAgent(1, 1, $errorMsg, $request);

    $this->assertSame(0, $result);
  }

  /**
   * @test
   * -# agents arrives as a scalar string instead of an array.
   * -# Verify scheduleAgent() returns 0 instead of throwing.
   */
  public function testScheduleAgentToleratesScalarAgents(): void
  {
    $request = new Request(['agents' => 'not-an-array']);
    $errorMsg = '';

    $result = $this->plugin->scheduleAgent(1, 1, $errorMsg, $request);

    $this->assertSame(0, $result);
  }

  /**
   * @test
   * -# Both deciderRules and agents malformed at once.
   * -# Verify scheduleAgent() returns 0 instead of throwing.
   */
  public function testScheduleAgentToleratesBothScalarParams(): void
  {
    $request = new Request(['deciderRules' => 'x', 'agents' => 'y']);
    $errorMsg = '';

    $result = $this->plugin->scheduleAgent(1, 1, $errorMsg, $request);

    $this->assertSame(0, $result);
  }

  /**
   * @test
   * -# Well-formed empty arrays (the normal "nothing selected" case).
   * -# Verify scheduleAgent() still returns 0 (regression guard: the fix
   *    must not change behavior for well-formed requests).
   */
  public function testScheduleAgentStillReturnsZeroForEmptyArrays(): void
  {
    $request = new Request(['deciderRules' => [], 'agents' => []]);
    $errorMsg = '';

    $result = $this->plugin->scheduleAgent(1, 1, $errorMsg, $request);

    $this->assertSame(0, $result);
  }

  /**
   * @test
   * -# agents arrives as a scalar string instead of an array.
   * -# Verify addScannerDependencies() leaves $dependencies untouched
   *    instead of throwing, when no Check_agent_* flags are set either.
   */
  public function testAddScannerDependenciesToleratesScalarAgents(): void
  {
    $request = new Request(['agents' => 'not-an-array']);
    $dependencies = [];

    Reflectory::invokeObjectsMethodnameWith(
      $this->plugin, 'addScannerDependencies', [&$dependencies, $request]
    );

    $this->assertSame([], $dependencies);
  }

  /**
   * @test
   * -# agents is a well-formed array containing agent_nomos.
   * -# Verify it is still added as a dependency (regression guard).
   */
  public function testAddScannerDependenciesStillAddsFromWellFormedArray(): void
  {
    $request = new Request(['agents' => ['agent_nomos']]);
    $dependencies = [];

    Reflectory::invokeObjectsMethodnameWith(
      $this->plugin, 'addScannerDependencies', [&$dependencies, $request]
    );

    $this->assertSame(['agent_nomos'], $dependencies);
  }

  /**
   * @test
   * -# Check_agent_monk=1 is set directly, agents[] is scalar.
   * -# Verify the Check_agent_* path still works even though the agents[]
   *    fallback is malformed.
   */
  public function testAddScannerDependenciesChecksFlagSurvivesScalarAgents(): void
  {
    $request = new Request(['Check_agent_monk' => 1, 'agents' => 'not-an-array']);
    $dependencies = [];

    Reflectory::invokeObjectsMethodnameWith(
      $this->plugin, 'addScannerDependencies', [&$dependencies, $request]
    );

    $this->assertSame(['agent_monk'], $dependencies);
  }

  /**
   * @test
   * -# licenseTypeConc arrives as an array instead of a string
   *    (e.g. ?licenseTypeConc[]=x on the query string).
   * -# Verify getLicenseTypeConf() returns "" instead of throwing.
   */
  public function testGetLicenseTypeConfToleratesArrayParam(): void
  {
    global $SysConf;
    $SysConf['SYSCONFIG']['LicenseTypes'] = 'Permissive,Copyleft';

    $request = new Request(['licenseTypeConc' => ['Permissive']]);

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->plugin, 'getLicenseTypeConf', [$request]
    );

    $this->assertSame('', $result);
  }

  /**
   * @test
   * -# licenseTypeConc is a well-formed string matching a configured type.
   * -# Verify it is still returned (regression guard).
   */
  public function testGetLicenseTypeConfStillReturnsValidType(): void
  {
    global $SysConf;
    $SysConf['SYSCONFIG']['LicenseTypes'] = 'Permissive,Copyleft';

    $request = new Request(['licenseTypeConc' => 'Permissive']);

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->plugin, 'getLicenseTypeConf', [$request]
    );

    $this->assertSame('Permissive', $result);
  }

  /**
   * @test
   * -# licenseTypeConc is a well-formed string NOT in the configured list.
   * -# Verify "" is returned (regression guard).
   */
  public function testGetLicenseTypeConfRejectsUnknownType(): void
  {
    global $SysConf;
    $SysConf['SYSCONFIG']['LicenseTypes'] = 'Permissive,Copyleft';

    $request = new Request(['licenseTypeConc' => 'Unknown']);

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->plugin, 'getLicenseTypeConf', [$request]
    );

    $this->assertSame('', $result);
  }
}
