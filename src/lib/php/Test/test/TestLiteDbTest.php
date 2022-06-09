<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Test;

class TestLiteDbTest extends \PHPUnit\Framework\TestCase
{

  public function testGetDbManager()
  {
    $testDb = new TestLiteDb();
    $this->assertInstanceOf('Fossology\Lib\Db\DbManager', $testDb->getDbManager());
  }

  public function testCreatePlainTables()
  {
    $testDb = new TestLiteDb();
    $testDb->createPlainTables(array('tag'));
    $dbManager = $testDb->getDbManager();

    $dbManager->queryOnce("insert into tag (tag_pk,tag,tag_desc) values (1,'hello','world')");
    $tag1 = $dbManager->getSingleRow('select * from tag where tag_pk=1');
    assertThat($tag1,hasKey('tag_desc'));
    assertThat($tag1['tag_desc'],is('world'));
  }

  public function testInsertData()
  {
    $testDb = new TestLiteDb();
    $testDb->createPlainTables(array('perm_upload'));
    $testDb->insertData(array('perm_upload'));
    $tag1 = $testDb->getDbManager()->getSingleRow('select perm from perm_upload where perm_upload_pk=1');
    assertThat($tag1,hasKey('perm'));
    assertThat($tag1['perm'],is(10));
  }
}
