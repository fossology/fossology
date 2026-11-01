<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Test\TestPgDb;

require_once dirname(__DIR__, 3) . '/lib/php/Plugin/FO_Plugin.php';
require_once dirname(__DIR__, 3) . '/lib/php/common-plugin.php';
require_once dirname(__DIR__, 3) . '/lib/php/common-menu.php';
require_once dirname(__DIR__, 3) . '/lib/php/common-ui.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class DashboardTest extends \PHPUnit\Framework\TestCase
{
  private $testDb;
  private $dashboard;
  private $connections = [];

  protected function setUp(): void
  {
    $this->testDb = new TestPgDb('dashboard' . uniqid());
    $GLOBALS['Plugins'] = [];
    require_once __DIR__ . '/../../ui/admin-dashboard-general.php';
    $this->dashboard = $GLOBALS['Plugins']['dashboard'];
  }

  protected function tearDown(): void
  {
    foreach ($this->connections as $connection) {
      pg_close($connection);
    }
    if ($this->testDb !== null) {
      $this->testDb->fullDestruct();
    }
  }

  public function testDatabaseMetricsUsesConnectedDatabase()
  {
    $dbManager = $this->testDb->getDbManager();
    $database = $dbManager->getSingleRow(
      'SELECT current_database() AS name', [], __METHOD__ . '.name'
    );
    $this->assertNotSame('fossology', $database['name']);
    $expected = $dbManager->getSingleRow(
      'SELECT pg_database_size($1) AS size', [$database['name']],
      __METHOD__ . '.size'
    );

    $metrics = $this->dashboard->DatabaseMetrics(true);

    $this->assertCount(3, $metrics);
    $this->assertSame(['metric', 'total'], array_keys($metrics[0]));
    $this->assertSame('FOSSology database size', $metrics[0]['metric']);
    $this->assertSame($expected['size'], $metrics[0]['total']);
    $this->assertStringContainsString(
      HumanSize($expected['size']), $this->dashboard->DatabaseMetrics()
    );
  }

  public function testDatabaseQueriesUsesConnectedDatabase()
  {
    $config = str_replace(';', ' ', file_get_contents(
      $this->testDb->getFossSysConf() . '/Db.conf'
    ));
    $connected = pg_connect($config, PGSQL_CONNECT_FORCE_NEW);
    $this->connections[] = $connected;
    $connectedPid = pg_get_pid($connected);
    pg_query($connected, 'SELECT 1');

    $other = pg_connect(
      preg_replace('/dbname=\S+/', 'dbname=postgres', $config),
      PGSQL_CONNECT_FORCE_NEW
    );
    $this->connections[] = $other;
    $otherPid = pg_get_pid($other);
    pg_query($other, 'SELECT 1');

    $queries = $this->dashboard->DatabaseQueries(true);
    $pids = array_map('intval', array_column($queries, 'pid'));

    $this->assertContains($connectedPid, $pids);
    $this->assertNotContains($otherPid, $pids);
    $this->assertSame(['pid', 'query', 'startTime', 'elapsed'],
      array_keys($queries[0]));
    $sortedPids = $pids;
    sort($sortedPids);
    $this->assertSame($sortedPids, $pids);
    $this->assertStringContainsString(
      "<td class='dashboard'>$connectedPid</td>",
      $this->dashboard->DatabaseQueries()
    );
    $this->assertStringNotContainsString(
      "<td class='dashboard'>$otherPid</td>",
      $this->dashboard->DatabaseQueries()
    );
  }
}
