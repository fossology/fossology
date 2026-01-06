<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;

class SysConfigDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  
  /** @var DbManager */
  private $dbManager;
  
  /** @var SysConfigDao */
  private $sysConfigDao;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = &$this->testDb->getDbManager();

    $this->testDb->createPlainTables(array('sysconfig'));

    $this->sysConfigDao = new SysConfigDao($this->dbManager);
    
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb = null;
    $this->dbManager = null;
  }

  /**
   * Test basic configuration storage and retrieval
   */
  public function testSetAndGetConfigValue()
  {
    $group = 'TestGroup';
    $name = 'TestSetting';
    $value = 'TestValue';

    // Insert a config value
    $this->dbManager->insertTableRow('sysconfig', array(
      'variablename' => $name,
      'conf_value' => $value,
      'ui_label' => 'Test Setting',
      'vartype' => 1,
      'group_name' => $group,
      'group_order' => 1,
      'description' => 'A test configuration',
      'validation_function' => null,
      'user_can_access' => 1
    ));

    // Retrieve it
    $sql = "SELECT conf_value FROM sysconfig WHERE variablename = $1";
    $result = $this->dbManager->getSingleRow($sql, array($name));
    
    assertThat($result['conf_value'], is($value));
    $this->addToAssertionCount(1);
  }

  /**
   * Test retrieving configuration by group
   */
  public function testGetConfigByGroup()
  {
    $group = 'DatabaseSettings';

    // Insert multiple config values in the same group
    $configs = array(
      array('db_host', 'localhost', 'Database Host'),
      array('db_port', '5432', 'Database Port'),
      array('db_name', 'fossology', 'Database Name')
    );

    foreach ($configs as $config) {
      $this->dbManager->insertTableRow('sysconfig', array(
        'variablename' => $config[0],
        'conf_value' => $config[1],
        'ui_label' => $config[2],
        'vartype' => 1,
        'group_name' => $group,
        'group_order' => 1,
        'description' => 'Database configuration',
        'validation_function' => null,
        'user_can_access' => 1
      ));
    }

    // Retrieve all configs from this group
    $sql = "SELECT variablename, conf_value FROM sysconfig WHERE group_name = $1 ORDER BY variablename";
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt, array($group));
    
    $results = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    
    assertThat(count($results), is(3));
    $this->addToAssertionCount(1);
  }

  /**
   * Test updating a configuration value
   */
  public function testUpdateConfigValue()
  {
    $name = 'MaxUploadSize';
    $initialValue = '100MB';
    $newValue = '500MB';

    // Insert initial config
    $this->dbManager->insertTableRow('sysconfig', array(
      'variablename' => $name,
      'conf_value' => $initialValue,
      'ui_label' => 'Max Upload Size',
      'vartype' => 1,
      'group_name' => 'Upload',
      'group_order' => 1,
      'description' => 'Maximum upload size',
      'validation_function' => null,
      'user_can_access' => 1
    ));

    // Update the value
    $sql = "UPDATE sysconfig SET conf_value = $1 WHERE variablename = $2";
    $this->dbManager->getSingleRow($sql, array($newValue, $name));

    // Verify the update
    $sql = "SELECT conf_value FROM sysconfig WHERE variablename = $1";
    $result = $this->dbManager->getSingleRow($sql, array($name));
    
    assertThat($result['conf_value'], is($newValue));
    $this->addToAssertionCount(1);
  }

  /**
   * Test different variable types
   */
  public function testDifferentVariableTypes()
  {
    // Insert configs with different types
    $configs = array(
      array('StringVar', 'text_value', 1),  // String type
      array('IntVar', '42', 2),              // Int type
      array('BoolVar', 'true', 4)            // Bool type
    );

    foreach ($configs as $config) {
      $this->dbManager->insertTableRow('sysconfig', array(
        'variablename' => $config[0],
        'conf_value' => $config[1],
        'ui_label' => $config[0],
        'vartype' => $config[2],
        'group_name' => 'Types',
        'group_order' => 1,
        'description' => 'Type test',
        'validation_function' => null,
        'user_can_access' => 1
      ));
    }

    // Verify different types were stored
    $sql = "SELECT COUNT(DISTINCT vartype) as type_count FROM sysconfig WHERE group_name = $1";
    $result = $this->dbManager->getSingleRow($sql, array('Types'));
    
    assertThat($result['type_count'], is('3'));
    $this->addToAssertionCount(1);
  }

  /**
   * Test user access control flags
   */
  public function testUserAccessControl()
  {
    // Insert some configs with different access levels
    $this->dbManager->insertTableRow('sysconfig', array(
      'variablename' => 'PublicSetting',
      'conf_value' => 'public_value',
      'ui_label' => 'Public Setting',
      'vartype' => 1,
      'group_name' => 'Access',
      'group_order' => 1,
      'description' => 'Public config',
      'validation_function' => null,
      'user_can_access' => 1
    ));

    $this->dbManager->insertTableRow('sysconfig', array(
      'variablename' => 'AdminSetting',
      'conf_value' => 'admin_value',
      'ui_label' => 'Admin Setting',
      'vartype' => 1,
      'group_name' => 'Access',
      'group_order' => 1,
      'description' => 'Admin only config',
      'validation_function' => null,
      'user_can_access' => 0
    ));

    // Count user-accessible configs
    $sql = "SELECT COUNT(*) as cnt FROM sysconfig WHERE group_name = $1 AND user_can_access = 1";
    $result = $this->dbManager->getSingleRow($sql, array('Access'));
    
    assertThat($result['cnt'], is('1'));
    $this->addToAssertionCount(1);
  }
}
