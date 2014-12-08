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

namespace Fossology\Lib\Db;

use Mockery as M;
use Mockery\MockInterface;

abstract class DbManagerTest extends \PHPUnit_Framework_TestCase
{
  /** @var Driver|MockInterface */
  protected $driver;
  /** @var Logger|MockInterface */
  protected $logger;
  /** @var DbManager */
  protected $dbManager;

  function setUp()
  {
    $this->driver = M::mock('Fossology\\Lib\\Db\\Driver');
    $this->driver->shouldReceive('booleanToDb')->with(true)->andReturn('t');
    $this->driver->shouldReceive('booleanToDb')->with(false)->andReturn('f');
    $this->driver->shouldReceive('escapeString')->andReturnUsing(function($v){return pg_escape_string($v);});

    $this->logger = M::mock('Monolog\\Logger');
    $this->logger->shouldReceive('addDebug');
    
    // $this->dbManager->setDriver($this->driver);
  }

  function tearDown()
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
  
  /**
   * @expectedException \Exception
   */
  function testCommitTransaction()
  {
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
    $this->logger->shouldReceive('addDebug')->with("/executing '$sqlStmt' took /");
    $this->dbManager->flushStats();
  }
  
  abstract function testCreateMap();
  
  function testExistsDb_no()
  {
    $this->driver->shouldReceive('existsTable')->with('/dTable/')->andReturn(FALSE);
    $existsTable = $this->dbManager->existsTable('badTable');
    assertThat($existsTable, is(FALSE));
  }
  
  function testExistsDb_yes()
  {
    $this->driver->shouldReceive('existsTable')->with('/dTable/')->andReturn(TRUE);
    $existsTable = $this->dbManager->existsTable('goodTable');
    assertThat($existsTable, is(TRUE));
  }
  
  function testExistsDb_hack()
  {
    $exceptionThrown = false;
    try {
      $this->dbManager->existsTable("goodTable' OR 3<'4");
    }
    catch(\Exception $e) {
      $exceptionThrown = TRUE;
    }
    assertThat($exceptionThrown, is(TRUE));
  }

  
} 