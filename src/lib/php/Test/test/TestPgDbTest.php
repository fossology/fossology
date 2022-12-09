<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Test;

class TestPgDbTest extends \PHPUnit\Framework\TestCase
{

  public function testIfTestDbIsCreated()
  {
    return;
    $this->markTestSkipped();
    $dbName = 'fosstestone';
    exec($cmd="dropdb -Ufossy -hlocalhost $dbName", $cmdOut, $cmdRtn);
    if ($cmdRtn != 0) {
      echo $cmdOut;
    }
    $testDb = new TestPgDb();
    exec($cmd="psql -Ufossy -hlocalhost -l | grep -q $dbName", $cmdOut, $cmdRtn);
    assertThat($cmdRtn,is(0));
  }

  public function testGetDbManager()
  {
    $testDb = new TestPgDb();
    $this->assertInstanceOf('Fossology\Lib\Db\DbManager', $testDb->getDbManager());
  }

  public function testCreatePlainTables()
  {
    $testDb = new TestPgDb();
    $testDb->createPlainTables(array('tag'));
    $dbManager = $testDb->getDbManager();

    $dbManager->queryOnce("insert into tag (tag_pk,tag,tag_desc) values (1,'hello','world')");
    $tag1 = $dbManager->getSingleRow('select * from tag where tag_pk=1');
    assertThat($tag1,hasKey('tag_desc'));
    assertThat($tag1['tag_desc'],is('world'));
  }

  public function testInsertData()
  {
    $testDb = new TestPgDb();
    $testDb->createPlainTables(array('perm_upload'));
    $testDb->insertData(array('perm_upload'));
    $tag1 = $testDb->getDbManager()->getSingleRow('select perm from perm_upload where perm_upload_pk=1');
    assertThat($tag1,hasKey('perm'));
    assertThat($tag1['perm'],is(10));
  }
}
