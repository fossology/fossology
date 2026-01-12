<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Monolog\Logger;

/**
 * Test class for SysConfigDao
 */
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

    $logger = new Logger("SysConfigDaoTest");
    $this->sysConfigDao = new SysConfigDao($this->dbManager, $logger);

    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb = null;
    $this->dbManager = null;
  }

  private function addConfig($name, $val, $group = 'Test', $type = 2)
  {
    $this->dbManager->insertTableRow('sysconfig', array(
      'variablename' => $name,
      'conf_value' => $val,
      'ui_label' => $name,
      'vartype' => $type,
      'group_name' => $group,
      'group_order' => 1,
      'description' => "Test config for $name",
      'validation_function' => null
    ));
  }

  /**
   * Test basic config data retrieval
   */
  public function testGetConfigData()
  {
    $this->addConfig('Setting1', 'val1', 'GroupA');
    $this->addConfig('Setting2', 'val2', 'GroupB');
    $this->addConfig('Setting3', 'val3', 'GroupA');

    $configs = $this->sysConfigDao->getConfigData();

    assertThat(count($configs), is(3));
    // Data should be ordered by group_name
    assertThat($configs[0]['variablename'], isOneOf('Setting1', 'Setting3'));
    $this->addToAssertionCount(2);
  }

  /**
   * Test banner message retrieval
   */
  public function testGetBannerData()
  {
    $msg = 'System maintenance tonight';
    $this->addConfig('BannerMsg', $msg);

    $banner = $this->sysConfigDao->getBannerData();

    assertThat($banner, is($msg));
    $this->addToAssertionCount(1);
  }

  /**
   * Test getCustomiseData formatting
   */
  public function testGetCustomiseData()
  {
    $this->addConfig('AppName', 'FOSSology', 'UI', 2);
    $this->addConfig('MaxFileSize', '100', 'Upload', 1);

    $rawData = $this->sysConfigDao->getConfigData();
    $customData = $this->sysConfigDao->getCustomiseData($rawData);

    assertThat(count($customData), greaterThan(0));
    assertThat($customData[0]['key'], startsWith('AppName') || startsWith('MaxFileSize'));
    $this->addToAssertionCount(2);
  }

  /**
   * Test updating a configuration value
   */
  public function testUpdateConfig()
  {
    $varName = 'TestSetting';
    $oldVal = 'old_value';
    $newVal = 'new_value';

    $this->addConfig($varName, $oldVal);

    // Use the DAO to update it
    list($success, $key) = $this->sysConfigDao->UpdateConfigData(array(
      'key' => $varName,
      'value' => $newVal
    ));

    assertThat($success, is(true));
    assertThat($key, is($varName));

    // Verify it was actually updated
    $allConfigs = $this->sysConfigDao->getConfigData();
    $found = false;
    foreach ($allConfigs as $conf) {
      if ($conf['variablename'] === $varName) {
        assertThat($conf['conf_value'], is($newVal));
        $found = true;
      }
    }
    assertThat($found, is(true));
    $this->addToAssertionCount(4);
  }

  /**
   * Updating with same value should still succeed
   */
  public function testUpdateConfigSameValue()
  {
    $varName = 'UnchangedSetting';
    $val = 'stays_same';

    $this->addConfig($varName, $val);

    list($success, $key) = $this->sysConfigDao->UpdateConfigData(array(
      'key' => $varName,
      'value' => $val
    ));

    assertThat($success, is(true));
    $this->addToAssertionCount(1);
  }
}
