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

namespace Fossology\Lib\Proxy;

use Mockery as M;
use Fossology\Lib\Db\DbManager;

class DbViewProxyTest extends \PHPUnit_Framework_TestCase
{
  private $dbViewName;
  private $dbViewQuery;
  /** @var DbViewProxy */
  private $dbViewDao;
  private $dbManagerMock;

  public function setUp()
  {
    $this->dbViewDao = new DbViewProxy($this->dbViewQuery, $this->dbViewName);
    global $container;
    $container = M::mock('ContainerBuilder');
    $this->dbManagerMock = M::mock(DbManager::classname());
    $container->shouldReceive('get')->withArgs(array('db.manager'))->andReturn($this->dbManagerMock);
  }

  public function tearDown()
  {
    M::close();
  }

  public function testGetDbViewName()
  {
    assertThat($this->dbViewDao->getDbViewName(),is($this->dbViewName));
  }
  
  public function testMaterialize()
  {
    $this->dbManagerMock->shouldReceive('queryOnce')->with("CREATE TEMPORARY TABLE $this->dbViewName AS $this->dbViewQuery");
    $this->dbViewDao->materialize();
  }

  public function testMaterializeTwice()
  {
    $this->dbManagerMock->shouldReceive('queryOnce')->once();
    $this->dbViewDao->materialize();
    $this->dbViewDao->materialize();
  }

  public function testUnmaterializeAfterMaterialize()
  {
    $this->dbManagerMock->shouldReceive('queryOnce');
    $this->dbViewDao->materialize();
    $this->dbManagerMock->shouldReceive('queryOnce')->with("DROP TABLE $this->dbViewName");
    $this->dbViewDao->unmaterialize();
  }
  
  public function testUnmaterializeWithoutMaterialize()
  {
    $this->dbManagerMock->shouldReceive('queryOnce')->never();
    $this->dbViewDao->unmaterialize();
  } 
    
  public function testAsCTE()
  {
    assertThat($this->dbViewDao->asCTE(),is("WITH $this->dbViewName AS (".$this->dbViewQuery.")"));
  }
  
}