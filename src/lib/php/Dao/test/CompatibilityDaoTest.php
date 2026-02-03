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

class CompatibilityDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;
  /** @var CompatibilityDao */
  private $compatibilityDao;
  /** @var integer */
  private $assertCountBefore;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = $this->testDb->getDbManager();
    $this->logger = new Logger("test");
    $licenseDao = M::mock('Fossology\Lib\Dao\LicenseDao');
    $agentDao = M::mock('Fossology\Lib\Dao\AgentDao');

    $this->testDb->createPlainTables(array('license_rules'));

    $this->compatibilityDao = new CompatibilityDao($this->dbManager, $this->logger, $licenseDao, $agentDao);

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

  public function testInsertRule()
  {
    $this->compatibilityDao->insertRule(1, 2, 'type1', 'type2', 'test comment', true);
    $rules = $this->compatibilityDao->getAllRules();
    assertThat(count($rules), is(1));
    assertThat($rules[0]['comment'], is('test comment'));
  }

  public function testGetAllRules()
  {
    $this->dbManager->insertTableRow('license_rules', array('first_rf_fk' => 1, 'second_rf_fk' => 2, 'comment' => 'rule1'));
    $this->dbManager->insertTableRow('license_rules', array('first_rf_fk' => 3, 'second_rf_fk' => 4, 'comment' => 'rule2'));
    
    $rules = $this->compatibilityDao->getAllRules();
    assertThat(count($rules), is(2));
  }

  public function testGetTotalRulesCount()
  {
    $this->dbManager->insertTableRow('license_rules', array('comment' => 'rule1'));
    $count = $this->compatibilityDao->getTotalRulesCount();
    assertThat($count, is(1));
  }

  public function testDeleteRule()
  {
    $id = $this->dbManager->insertTableRow('license_rules', array('comment' => 'to delete'), 'test', 'lr_pk');
    $res = $this->compatibilityDao->deleteRule($id);
    assertThat($res, is(true));
    assertThat($this->compatibilityDao->getTotalRulesCount(), is(0));
  }
}
