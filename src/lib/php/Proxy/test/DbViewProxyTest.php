<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Proxy;

use Fossology\Lib\Db\DbManager;
use Mockery as M;

class DbViewProxyTest extends \PHPUnit\Framework\TestCase
{
  private $dbViewName = "foo";
  private $dbViewQuery = "select 3.14";
  /** @var DbViewProxy */
  private $dbViewDao;
  private $dbManagerMock;

  protected function setUp() : void
  {
    $this->dbViewDao = new DbViewProxy($this->dbViewQuery, $this->dbViewName);
    global $container;
    $container = M::mock('ContainerBuilder');
    $this->dbManagerMock = M::mock(DbManager::class);
    $container->shouldReceive('get')->withArgs(array('db.manager'))->andReturn($this->dbManagerMock);
  }

  protected function tearDown() : void
  {
    M::close();
  }

  public function testGetDbViewName()
  {
    assertThat($this->dbViewDao->getDbViewName(),is($this->dbViewName));
  }

  public function testMaterialize()
  {
    $this->dbManagerMock->shouldReceive('queryOnce')->with("CREATE TEMPORARY TABLE $this->dbViewName AS $this->dbViewQuery", M::any());
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
