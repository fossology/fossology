<?php
/*
Copyright (C) 2014, Siemens AG
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

class UserDaoTest extends \PHPUnit_Framework_TestCase
{
  /** @var TestLiteDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;

  public function setUp()
  {
    $this->testDb = new TestLiteDb();
    $this->dbManager = $this->testDb->getDbManager();
  }
  
  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
  }

  public function testGetUserGroupMap()
  {
    $this->testDb->createPlainTables(array('groups','group_user_member'));
    $this->testDb->insertData(array('groups','group_user_member'));
    
    $userDao = new UserDao($this->dbManager);
    $defaultGroups = $userDao->getUserGroupMap($userId=1);
    assertThat($defaultGroups, equalTo(array(1=>'Default User')));
  }
  
  public function testGetAdminGroupMap()
  {
    $this->testDb->createPlainTables(array('groups','group_user_member'));
    $this->testDb->insertData(array('groups','group_user_member'));
    defined('PLUGIN_DB_ADMIN') or define('PLUGIN_DB_ADMIN',10);
    $userDao = new UserDao($this->dbManager);
    $defaultGroups = $userDao->getAdminGroupMap($userId=2,$userLevel=PLUGIN_DB_ADMIN);
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
    $userDao = new UserDao($this->dbManager);

    $groups = $userDao->getDeletableAdminGroupMap($userId,$userLevel=PLUGIN_DB_ADMIN);
    assertThat($groups, equalTo(array($deletable['group_pk']=>$deletable['group_name'])));

    $groups = $userDao->getDeletableAdminGroupMap($userId);
    assertThat($groups, equalTo(array($deletable['group_pk']=>$deletable['group_name'])));
    
    $groups = $userDao->getDeletableAdminGroupMap($userId+1);
    assertThat($groups, equalTo(array()));
  }  

}
