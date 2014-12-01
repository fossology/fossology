<?php
/*
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;
use Monolog\Logger;

class AgentDaoTest extends \PHPUnit_Framework_TestCase {

  private $uploadId = 25;
  private $olderAgentId = 3;
  private $otherAgentId = 4;
  private $agentId = 5;
  private $incompleteAgentId = 6;

  private $agentName = "agentName";
  private $otherAgentName = "otherAgentName";

  private $agentRev = "<agentRev>";
  private $olderAgentRev = "<olderAgentRev>";
  private $otherAgentRev = "<otherAgentRev>";
  private $incompleteAgentRev = "<incompleteAgentRev>";

  private $agentDesc = "<agentDesc>";
  private $otherAgentDesc = "<otherAgentDesc>";

  private $agentEnabled = true;

  /** @var TestPgDb */
  private $testDb;

  /** @var DbManager */
  private $dbManager;

  /** @var Logger|M\MockInterface */
  private $logger;

  /** @var AgentDao */
  private $agentsDao;

  /** @var AgentRef */
  private $agent;
  /** @var AgentRef */
  private $olderAgent;
  /** @var AgentRef */
  private $otherAgent;
  /** @var AgentRef */
  private $incompleteAgent;

  public function setUp() {
    $this->dbManager = M::mock(DbManager::classname());
    $this->logger = M::mock('Monolog\Logger');

    $this->testDb = new TestPgDb();
    $this->dbManager = &$this->testDb->getDbManager();

    $this->agent = new AgentRef($this->agentId, $this->agentName, $this->agentRev);
    $this->olderAgent = new AgentRef($this->olderAgentId, $this->agentName, $this->olderAgentRev);
    $this->otherAgent = new AgentRef($this->otherAgentId, $this->otherAgentName, $this->otherAgentRev);
    $this->incompleteAgent = new AgentRef($this->incompleteAgentId, $this->agentName, $this->incompleteAgentRev);

    $this->testDb->createPlainTables(
        array(
            'agent'
        ));

    $agentArray = array(
        array($this->olderAgentId, $this->agentName, $this->olderAgentRev, $this->agentDesc, $this->dbManager->booleanToDb($this->agentEnabled)),
        array($this->otherAgentId, $this->otherAgentName, $this->otherAgentRev, $this->otherAgentDesc, $this->dbManager->booleanToDb($this->agentEnabled)),
        array($this->agentId, $this->agentName, $this->agentRev, $this->agentDesc, $this->dbManager->booleanToDb($this->agentEnabled)),
        array($this->incompleteAgentId, $this->agentName, $this->incompleteAgentRev, $this->agentDesc, $this->dbManager->booleanToDb($this->agentEnabled)),
    );
    foreach ($agentArray as $agentRow)
    {
      $this->dbManager->insertInto('agent', 'agent_pk, agent_name, agent_rev, agent_desc, agent_enabled', $agentRow);
    }
    $this->agentsDao = new AgentDao($this->dbManager, $this->logger);

    $arsTableName = $this->agentName . AgentDao::ARS_TABLE_SUFFIX;
    $this->dbManager->queryOnce("create table " . $arsTableName . " (ars_pk int, agent_fk int, upload_fk int, ars_success bool)");
    $arsArray = array(
      array(1, $this->olderAgentId, $this->uploadId, $this->dbManager->booleanToDb(true)),
      array(2, $this->agentId, $this->uploadId, $this->dbManager->booleanToDb(true)),
      array(3, $this->incompleteAgentId, $this->uploadId, $this->dbManager->booleanToDb(false))
    );
    foreach ($arsArray as $arsRow)
    {
      $this->dbManager->insertInto($arsTableName, 'ars_pk, agent_fk, upload_fk, ars_success', $arsRow);
    }

    $arsTableName = $this->otherAgentName . AgentDao::ARS_TABLE_SUFFIX;
    $this->dbManager->queryOnce("create table " . $arsTableName . " (ars_pk int, agent_fk int, upload_fk int, ars_success bool)");
    $arsArray = array(
        array(1, $this->otherAgentId, $this->uploadId, $this->dbManager->booleanToDb(true)),
    );
    foreach ($arsArray as $arsRow)
    {
      $this->dbManager->insertInto($arsTableName, 'ars_pk, agent_fk, upload_fk, ars_success', $arsRow);
    }
  }

  public function tearDown() {
    $this->dbManager->queryOnce("drop table " . $this->agentName . AgentDao::ARS_TABLE_SUFFIX);
    $this->dbManager->queryOnce("drop table " . $this->otherAgentName . AgentDao::ARS_TABLE_SUFFIX);

    $this->dbManager = null;
    $this->testDb = null;

    M::close();
  }

  public function testGetCurrentAgent()
  {
    assertThat($this->agentsDao->getCurrentAgent($this->agentName), is($this->incompleteAgent));
  }

  public function testGetSuccessfulAgentRuns()
  {
    assertThat($this->agentsDao->getSuccessfulAgentRuns($this->agentName, $this->uploadId), is(array($this->agent, $this->olderAgent)));
  }

  public function testGetLatestAgentResultForUpload()
  {
    $latestAgentResults = $this->agentsDao->getLatestAgentResultForUpload($this->uploadId, array($this->agentName, $this->otherAgentName));
    assertThat($latestAgentResults, is(array(
      $this->agentName => $this->agentId,
      $this->otherAgentName => $this->otherAgentId
    )));
  }

  public function testGetRunningAgentIds()
  {
    assertThat($this->agentsDao->getRunningAgentIds($this->uploadId, $this->agentName), is(array($this->incompleteAgentId)));
  }

  public function testGetRunningAgentIdsForFinishedAgent()
  {
    assertThat($this->agentsDao->getRunningAgentIds($this->uploadId, $this->otherAgentName), is(emptyArray()));
  }

  public function testGetRunningAgentIdsForUnknownAgent()
  {
    assertThat($this->agentsDao->getRunningAgentIds($this->uploadId, "unknown"), is(emptyArray()));
  }

  public function testArsTableExists()
  {
    $this->assertTrue($this->agentsDao->arsTableExists($this->agentName));
  }

  public function testArsTableExistsReturnsFalseIfTableDoesNotExist()
  {
    $this->assertFalse($this->agentsDao->arsTableExists("unknown"));
  }

}
 