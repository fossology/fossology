<?php
/*
 SPDX-FileCopyrightText: © 2024 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;
use Monolog\Logger;

class SysConfigDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var SysConfigDao */
  private $sysConfigDao;
  /** @var integer */
  private $assertCountBefore;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = $this->testDb->getDbManager();
    $logger = new Logger("test");

    $this->sysConfigDao = new SysConfigDao($this->dbManager, $logger);

    $this->testDb->createPlainTables(array('sysconfig'));

    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
    M::close();
  }

  public function testGetBannerData()
  {
    $this->dbManager->insertTableRow('sysconfig', array('variablename' => 'BannerMsg', 'conf_value' => 'Hello World'));
    $banner = $this->sysConfigDao->getBannerData();
    assertThat($banner, is('Hello World'));
  }

  public function testUpdateConfigData()
  {
    $this->dbManager->insertTableRow('sysconfig', array('variablename' => 'TestVar', 'conf_value' => 'old', 'vartype' => 2));
    
    $res = $this->sysConfigDao->UpdateConfigData(array('key' => 'TestVar', 'value' => 'new'));
    assertThat($res[0], is(true));
    
    $data = $this->sysConfigDao->getConfigData();
    assertThat($data[0]['conf_value'], is('new'));
  }
}
