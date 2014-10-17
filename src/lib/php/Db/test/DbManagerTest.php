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

class DbManagerTest extends \PHPUnit_Framework_TestCase
{
  /** @var Driver|MockInterface */
  private $driver;
  /** @var Logger|MockInterface */
  private $logger;
  /** @var DbManager */
  private $dbManager;

  function setUp()
  {
    $this->driver = M::mock('Fossology\\Lib\\Db\\Driver');
    $this->logger = M::mock('Monolog\\Logger'); // new Logger(__FILE__);
    $this->logger->shouldReceive('addDebug');
    $this->dbManager = new DbManager($this->logger);
    $this->dbManager->setDriver($this->driver);
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

  function testCommitTransaction()
  {
    $this->driver->shouldReceive("commit")->withNoArgs()->once();
    $this->dbManager->commit();
  }
  
  function testInsertTableRow()
  {
    $tableName = 'foo';
    $assocParams = array('cola'=>1,'colb'=>2);
    $sqlLog = 'bar';
    $preSql = "INSERT INTO $tableName (cola,colb) VALUES ($1,$2)";
    $this->driver->shouldReceive('prepare')->with($sqlLog,$preSql)->once();
    $this->driver->shouldReceive('execute')->with($sqlLog,array_values($assocParams))->once();
    $this->driver->shouldReceive('freeResult');
    $this->dbManager->insertTableRow($tableName,$assocParams,$sqlLog);
  }
  
  function testFlushStats()
  {
    $this->driver->shouldReceive('prepare');
    $sqlStmt = 'foo';
    $this->dbManager->prepare($sqlStmt,'SELECT elephant FROM africa');
    $this->logger->shouldReceive('addDebug')->with("/executing '$sqlStmt' took /");
    $this->dbManager->flushStats();
  }
  
  function testCreateMap()
  {
    $keyColumn = 'yek';
    $valueColumn = 'lav';
    $tableName = 'foo';
    $sqlLog = 'bar';
    $preSql = "select $keyColumn,$valueColumn from $tableName";
    $this->driver->shouldReceive('prepare')->with($sqlLog,$preSql)->once();
    $this->driver->shouldReceive('execute')->andReturn('fakeRes');
    $this->driver->shouldReceive('fetchArray')->andReturn(
            array($keyColumn=>'k0',$valueColumn=>'v0'),
            array($keyColumn=>'k1',$valueColumn=>'v1'),
            false
          );
    $this->driver->shouldReceive('freeResult');
    $map = $this->dbManager->createMap($tableName,$keyColumn,$valueColumn,$sqlLog);
    assertThat($map,hasKey('k0'));
    assertThat($map,EqualTo(array('k0'=>'v0','k1'=>'v1')));
  }
  
} 