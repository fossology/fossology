<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Db;

use Exception;
use Mockery as M;
use Mockery\MockInterface;

abstract class DbManagerTest extends \PHPUnit\Framework\TestCase
{
  /** @var Driver|MockInterface */
  protected $driver;
  /** @var Logger|MockInterface */
  protected $logger;
  /** @var DbManager */
  protected $dbManager;

  function setUp() : void
  {
    $this->driver = M::mock('Fossology\\Lib\\Db\\Driver');
    $this->driver->shouldReceive('booleanToDb')->with(true)->andReturn('t');
    $this->driver->shouldReceive('booleanToDb')->with(false)->andReturn('f');
    $this->driver->shouldReceive('escapeString')->andReturnUsing(function ($v){
      return pg_escape_string($v);
    });

    $this->logger = M::mock('Monolog\\Logger');
    $this->logger->shouldReceive('debug');
  }

  function tearDown() : void
  {
    M::close();
  }

  function testBeginTransaction()
  {
    $this->driver->shouldReceive("begin")->withNoArgs()->once();
    $this->dbManager->begin();
  }

  function testBeginTransactionTwice()
  {
    $this->driver->shouldReceive("begin")->withNoArgs()->once();
    $this->dbManager->begin();
    $this->dbManager->begin();
  }

  function testCommitTransaction()
  {
    $this->expectException(Exception::class);
    $this->driver->shouldReceive("commit")->withNoArgs()->never();
    $this->dbManager->commit();
  }

  function testBeginAndCommitTransaction()
  {
    $this->driver->shouldReceive("begin")->withNoArgs()->once();
    $this->dbManager->begin();
    $this->driver->shouldReceive("commit")->withNoArgs()->once();
    $this->dbManager->commit();
  }

  abstract function testInsertTableRow();

  function testFlushStats()
  {
    $this->driver->shouldReceive('prepare');
    $sqlStmt = 'foo';
    $this->dbManager->prepare($sqlStmt,'SELECT elephant FROM africa');
    $this->logger->shouldReceive('addDebug')->with(M::pattern("/executing '$sqlStmt' took /"));
    $this->dbManager->flushStats();
  }

  abstract function testCreateMap();

  function testExistsDb_no()
  {
    $this->driver->shouldReceive('existsTable')->with(M::pattern('/dTable/'))->andReturn(FALSE);
    $existsTable = $this->dbManager->existsTable('badTable');
    assertThat($existsTable, is(FALSE));
  }

  function testExistsDb_yes()
  {
    $this->driver->shouldReceive('existsTable')->with(M::pattern('/dTable/'))->andReturn(TRUE);
    $existsTable = $this->dbManager->existsTable('goodTable');
    assertThat($existsTable, is(TRUE));
  }

  function testExistsDb_hack()
  {
    $this->expectException(Exception::class);
    $this->dbManager->existsTable("goodTable' OR 3<'4");
  }

  function testInsertTableRowReturning()
  {
    $this->driver->shouldReceive('query');
    $this->driver->shouldReceive('prepare');
    $this->driver->shouldReceive('execute')->with("logging.returning:id", array("mouse"))->andReturn();
    $this->driver->shouldReceive('fetchArray')->withAnyArgs()->andReturn(array("id" => 23, "animal" => "mouse"));
    $this->driver->shouldReceive('freeResult')->withAnyArgs();

    $returnId = $this->dbManager->insertInto('europe', 'animal', array('mouse'), $log='logging', 'id');
    assertThat($returnId,equalTo(23));
  }
}
