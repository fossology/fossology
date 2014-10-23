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

class SolidDbManagerTest extends \PHPUnit_Framework_TestCase
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
    $this->dbManager = new SolidDbManager($this->logger);
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
    $this->driver->shouldReceive('query')->once();
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
    $preSql = "/ $keyColumn, *$valueColumn /";
    $this->driver->shouldReceive('query')->andReturn('fakeRes');
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
  
  function testEvaluateStatement()
  {
    $this->driver->shouldReceive('booleanToDb')->with(true)->andReturn('t');
    $this->driver->shouldReceive('booleanToDb')->with(false)->andReturn('f');
    $this->driver->shouldReceive('query');
    $sqlStmt = 'SELECT pet FROM africa WHERE cervical=$1 AND class=$2 AND $3';
    $this->dbManager->prepare($stmt='statement',$sqlStmt);
    
    $reflection = new \ReflectionClass($this->dbManager->classname() );
    $method = $reflection->getMethod('evaluateStatement');
    $method->setAccessible(true);

    $params = array(7,'Mammalia',true);
    $sql = $method->invoke($this->dbManager,$stmt,$params);
    assertThat($sql, is('SELECT pet FROM africa WHERE cervical=7 AND class=\'Mammalia\' AND t') );
    
    $params = array(7,'Mammalia\'; SELECT * FROM passwords WHERE user like \'%',true);
    $sql = $method->invoke($this->dbManager,$stmt,$params);
    assertThat($sql, is('SELECT pet FROM africa WHERE cervical=7 AND class=\'Mammalia\'\'; SELECT * FROM passwords WHERE user like \'\'%\' AND t') );
  }  
    
  function testEvaluateStatement_exception()
  {
    $sqlStmt = 'SELECT pet FROM africa WHERE cervical=$1 AND class=$2';
    $this->dbManager->prepare($stmt='statement',$sqlStmt);
    
    $reflection = new \ReflectionClass($this->dbManager->classname() );
    $method = $reflection->getMethod('evaluateStatement');
    $method->setAccessible(true);
    
    $exceptionMsg = false;
    $params = array(7,'Mammalia','non-used parameter');
    try {
      $method->invoke($this->dbManager,$stmt,$params);
    }
    catch(\Exception $e) {
      $exceptionMsg = $e->getMessage();
    }
    assertThat($exceptionMsg, is('$3 not found in prepared statement'));
  }
  
} 