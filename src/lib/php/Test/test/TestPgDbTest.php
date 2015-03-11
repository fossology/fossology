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

namespace Fossology\Lib\Test;

class TestPgDbTest extends \PHPUnit_Framework_TestCase
{
  
  public function testIfTestDbIsCreated()
  {
    return;
    $this->markTestSkipped();
    $dbName = 'fosstestone';
    exec($cmd="dropdb -Ufossy -hlocalhost $dbName", $cmdOut, $cmdRtn);
    if($cmdRtn != 0)
    {
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
