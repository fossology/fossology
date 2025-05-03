<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;
use Monolog\Logger;

class AgentDaoTest extends \PHPUnit\Framework\TestCase
{

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

  protected function setUp() : void
  {
    $this->dbManager = M::mock(DbManager::class);
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
    foreach ($agentArray as $agentRow) {
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
    foreach ($arsArray as $arsRow) {
      $this->dbManager->insertInto($arsTableName, 'ars_pk, agent_fk, upload_fk, ars_success', $arsRow);
    }

    $arsTableName = $this->otherAgentName . AgentDao::ARS_TABLE_SUFFIX;
    $this->dbManager->queryOnce("create table " . $arsTableName . " (ars_pk int, agent_fk int, upload_fk int, ars_success bool)");
    $arsArray = array(
        array(1, $this->otherAgentId, $this->uploadId, $this->dbManager->booleanToDb(true)),
    );
    foreach ($arsArray as $arsRow) {
      $this->dbManager->insertInto($arsTableName, 'ars_pk, agent_fk, upload_fk, ars_success', $arsRow);
    }
  }

  protected function tearDown() : void
  {
    $this->dbManager->queryOnce("drop table " . $this->agentName . AgentDao::ARS_TABLE_SUFFIX);
    $this->dbManager->queryOnce("drop table " . $this->otherAgentName . AgentDao::ARS_TABLE_SUFFIX);

    $this->dbManager = null;
    $this->testDb = null;

    M::close();
  }

  public function testGetCurrentAgent()
  {
    assertThat($this->agentsDao->getCurrentAgentRef($this->agentName), is($this->incompleteAgent));
    $this->addToAssertionCount(1);
  }

  public function testGetSuccessfulAgentRuns()
  {
    assertThat($this->agentsDao->getSuccessfulAgentRuns($this->agentName, $this->uploadId), is(array($this->agent, $this->olderAgent)));
    $this->addToAssertionCount(1);
  }

  public function testGetLatestAgentResultForUpload()
  {
    global $container;
    $container = M::mock('ContainerBuilder');
    $this->dbManagerMock = M::mock(DbManager::class);
    $container->shouldReceive('get')->withArgs(array('db.manager'))->andReturn($this->dbManagerMock);

    $this->dbManagerMock->shouldReceive('prepare')->once();
    $this->dbManagerMock->shouldReceive('execute')->once();
    $this->dbManagerMock->shouldReceive('fetchArray')
            ->andReturn(array('agent_pk'=>$this->agentId,'agent_name'=>$this->agentName),
                    array('agent_pk'=>$this->otherAgentId,'agent_name'=>$this->otherAgentName),
                    false);
    $this->dbManagerMock->shouldReceive('freeResult')->once();

    $latestAgentResults = $this->agentsDao->getLatestAgentResultForUpload($this->uploadId, array($this->agentName, $this->otherAgentName));
    assertThat($latestAgentResults, is(array(
      $this->agentName => $this->agentId,
      $this->otherAgentName => $this->otherAgentId
    )));
    $this->addToAssertionCount(1);
  }

  public function testGetRunningAgentIds()
  {
    assertThat($this->agentsDao->getRunningAgentIds($this->uploadId, $this->agentName), is(array($this->incompleteAgentId)));
    $this->addToAssertionCount(1);
  }

  public function testGetRunningAgentIdsForFinishedAgent()
  {
    assertThat($this->agentsDao->getRunningAgentIds($this->uploadId, $this->otherAgentName), is(emptyArray()));
    $this->addToAssertionCount(1);
  }

  public function testGetRunningAgentIdsForUnknownAgent()
  {
    assertThat($this->agentsDao->getRunningAgentIds($this->uploadId, "unknown"), is(emptyArray()));
    $this->addToAssertionCount(1);
  }

  public function testArsTableExists()
  {
    $this->assertTrue($this->agentsDao->arsTableExists($this->agentName));
  }

  public function testArsTableExistsReturnsFalseIfTableDoesNotExist()
  {
    $this->assertFalse($this->agentsDao->arsTableExists("unknown"));
  }
  /**
   * Helper function to create an agent in the database for testing purposes.
   *
   * @param int $agentId The ID to assign to the agent. Defaults to 2.
   *
   * @return int The ID of the created agent.
   *
   * -# Inserts a new record into the `agent` table with the specified ID and attributes.
   * -# The inserted record includes fields such as `agent_name`, `agent_rev`, `agent_desc`, and `agent_enabled`.
   * -# Returns the ID of the created agent, which can be used for further testing or assertions.
   */
  private function createAgent($agentId = 2)
  {
    $agentName = "nomos";
    $agentRev = ".68097a";
    $agentDesc = "License Scanner";
    return $this->dbManager->insertTableRow("agent", array("agent_pk" => $agentId,"agent_name" => $agentName, "agent_rev" => $agentRev, "agent_desc" => $agentDesc, "agent_enabled" => true), null, 'agent_pk');
  }
  /**
   * @test
   * -# Test to retrieve the revision information of an agent by its ID
   *    AgentsDao::getAgentRev()
   * -# Create an agent to ensure there is an agent available for the test
   * -# Check that the agent revision is correctly retrieved and matches the expected value
   */
  public function testGetAgentRev()
  {
    $agentId = $this->createAgent();
    $result = $this->agentsDao->getAgentRev($agentId);
    $this->assertNotNull($result);
    $this->assertEquals( ".68097a",$result);
  }
  /**
   * @test
   * -# Test to retrieve the name of an agent by its ID
   *    AgentsDao::getAgentName()
   * -# Create an agent to ensure there is an agent available for the test
   * -# Check that the agent name is correctly retrieved and matches the expected value
   */
  public function testGetAgentName()
  {
    $agentId = $this->createAgent();
    $result = $this->agentsDao->getAgentName($agentId);
    $this->assertNotNull($result);
    $this->assertEquals("nomos",$result);
  }
  /**
   * @test
   * -# Test to renew the current agent for a specific agent type
   *    AgentsDao::renewCurrentAgent()
   * -# Ensure an agent is created before attempting to renew
   * -# Verify that the renewal operation returns a non-null result and is successful
   */
  public function testRenewCurrentAgent()
  {
    $this->createAgent();
    $result = $this->agentsDao->renewCurrentAgent("nomos");
    $this->assertNotNull($result);
    $this->assertTrue($result);
  }
  /**
   * @test
   * -# Test to retrieve the current agent ID for a specific agent type
   *    AgentsDao::getCurrentAgentId()
   * -# Ensure an agent is created before fetching the agent ID
   * -# Verify that the returned agent ID is not null and matches the expected ID
   */
  public function testGetCurrentAgentId()
  {
    $this->createAgent();
    $result = $this->agentsDao->getCurrentAgentId("nomos");
    $this->assertNOtNull($result);
    $this->assertEquals(2,$result);
  }
}
