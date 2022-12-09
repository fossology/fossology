<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Db;


class ModernDbManagerTest extends DbManagerTest
{

  function setUp() : void
  {
    parent::setUp();
    $this->dbManager = new ModernDbManager($this->logger);
    $this->dbManager->setDriver($this->driver);
  }

  function tearDown() : void
  {
    parent::tearDown();
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

  function testCreateMap()
  {
    $keyColumn = 'yek';
    $valueColumn = 'lav';
    $tableName = 'foo';
    $sqlLog = 'bar';
    $preSql = "/ $keyColumn, *$valueColumn /";
    $this->driver->shouldReceive('prepare')->with($sqlLog,\Mockery::pattern($preSql))->once();
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
