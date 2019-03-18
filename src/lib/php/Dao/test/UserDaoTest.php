<?php
/*
Copyright (C) 2014-2015, Siemens AG
Author: Steffen Weber

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

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestLiteDb;
use Monolog\Logger;

class UserDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestLiteDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;
  /** @var UserDao */
  private $userDao;
  /** @var assertCountBefore */
  private $assertCountBefore;

  protected function setUp()
  {
    $this->testDb = new TestLiteDb();
    $this->dbManager = $this->testDb->getDbManager();
    $this->logger = new Logger("test");
    $this->userDao = new UserDao($this->dbManager, $this->logger);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown()
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb = null;
    $this->dbManager = null;
  }

  public function testGetUserGroupMap()
  {
    $this->testDb->createPlainTables(array('groups','group_user_member'));
    $this->testDb->insertData(array('groups','group_user_member'));

    $defaultGroups = $this->userDao->getUserGroupMap($userId=1);
    assertThat($defaultGroups, equalTo(array(1=>'Default User')));
  }

  public function testGetAdminGroupMap()
  {
    $this->testDb->createPlainTables(array('groups','group_user_member'));
    $this->testDb->insertData(array('groups','group_user_member'));
    defined('PLUGIN_DB_ADMIN') or define('PLUGIN_DB_ADMIN',10);

    $defaultGroups = $this->userDao->getAdminGroupMap($userId=2,$userLevel=PLUGIN_DB_ADMIN);
    assertThat($defaultGroups, equalTo(array(1=>'Default User',2=>'fossy')));
  }


  public function testGetDeletableAdminGroupMap()
  {
    $this->testDb->createPlainTables(array('groups','group_user_member','users'));
    $username = 'testi';
    $userId = 101;
    $this->dbManager->insertTableRow('users',array('user_pk'=>$userId,'user_name'=>$username));
    $this->dbManager->insertTableRow('groups', array('group_pk'=>201,'group_name'=>$username));
    $this->dbManager->insertTableRow('group_user_member', array('group_fk'=>201,'user_fk'=>$userId));
    $deletable = array('group_pk'=>202,'group_name'=>'anyName');
    $this->dbManager->insertTableRow('groups', $deletable);
    $this->dbManager->insertTableRow('group_user_member', array('group_fk'=>202,'user_fk'=>$userId,'group_perm'=>1));

    $groupsAsAdmin = $this->userDao->getDeletableAdminGroupMap($userId,$userLevel=PLUGIN_DB_ADMIN);
    assertThat($groupsAsAdmin, equalTo(array($deletable['group_pk']=>$deletable['group_name'])));

    $groups = $this->userDao->getDeletableAdminGroupMap($userId);
    assertThat($groups, equalTo(array($deletable['group_pk']=>$deletable['group_name'])));

    $groupsAsForeign = $this->userDao->getDeletableAdminGroupMap($userId+1);
    assertThat($groupsAsForeign, equalTo(array()));
  }

  public function testAddGroup()
  {
    $this->dbManager->queryOnce('CREATE TABLE groups (group_pk integer NOT NULL PRIMARY KEY, group_name varchar(64))');
    $this->testDb->insertData(array('groups'));
    $groupId = $this->userDao->addGroup($groupName='newGroup');
    $row = $this->dbManager->getSingleRow('SELECT group_name FROM groups WHERE group_pk=$1',array($groupId));
    assertThat($row['group_name'], equalTo($groupName));
  }

  /**
   * @expectedException \Exception
   */
  public function testAddGroupFailIfAlreadyExists()
  {
    $this->testDb->createPlainTables(array('groups','users'));
    $this->testDb->insertData(array('groups','user'));
    $this->userDao->addGroup('fossy');
  }

  /**
   * @expectedException \Exception
   */
  public function testAddGroupFailEmptyName()
  {
    $this->testDb->createPlainTables(array('groups','users'));
    $this->testDb->insertData(array('groups','user'));
    $this->userDao->addGroup('');
  }

  public function testGetUserName()
  {
    $username = 'testi';
    $userId = 101;
    $this->testDb->createPlainTables(array('users'));
    $this->dbManager->insertTableRow('users',array('user_pk'=>$userId,'user_name'=>$username));
    $uName = $this->userDao->getUserName($userId);
    assertThat($uName,equalTo($username));
  }

  /**
   * @expectedException \Exception
   * @expectedExceptionMessage unknown user with id=101
   */
  public function testGetUserNameFail()
  {
    $this->testDb->createPlainTables(array('users'));
    $this->userDao->getUserName(101);
  }

  public function testGetGroupIdByName()
  {
    $this->testDb->createPlainTables(array('groups'));
    $this->testDb->insertData(array('groups'));
    $groupId = $this->userDao->getGroupIdByName('fossy');
    assertThat($groupId,equalTo(2));
  }

  public function testAddGroupMembership()
  {
    $this->testDb->createPlainTables(array('users','groups','group_user_member'));
    $this->testDb->insertData(array('users','groups','group_user_member'));
    $this->userDao->addGroupMembership($groupId=2,$userId=1);
    $map = $this->userDao->getUserGroupMap($userId);
    assertThat($map,hasKey($groupId));
  }
}
