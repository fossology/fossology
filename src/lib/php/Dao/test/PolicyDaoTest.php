<?php
/*
 SPDX-FileCopyrightText: © 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Exception;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestLiteDb;
use Monolog\Logger;

class PolicyDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestLiteDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;
  /** @var PolicyDao */
  private $policyDao;
  /** @var int */
  private $assertCountBefore;

  protected function setUp() : void
  {
    $this->testDb = new TestLiteDb();
    $this->dbManager = $this->testDb->getDbManager();
    $this->logger = new Logger("test");
    $this->policyDao = new PolicyDao($this->dbManager, $this->logger);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    
    // Create base tables
    $this->testDb->createPlainTables(array('users'));
    $this->dbManager->queryOnce('CREATE TABLE license_ref (rf_pk integer NOT NULL PRIMARY KEY, rf_shortname varchar(255))');
    $this->dbManager->queryOnce('CREATE TABLE license_policy (rf_fk integer NOT NULL UNIQUE, policy_rank integer CHECK (policy_rank IN (0,1,2)))');
    $this->dbManager->queryOnce('CREATE TABLE license_policy_log (id integer PRIMARY KEY AUTOINCREMENT, rf_fk integer, old_rank integer, new_rank integer, user_fk integer, timestamp timestamp DEFAULT current_timestamp, source varchar(50), request_ip varchar(45))');
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb = null;
    $this->dbManager = null;
  }

  public function testSetAndGetAllPolicies()
  {
    // Setup references
    $this->dbManager->insertTableRow('users', array('user_pk' => 1, 'user_name' => 'admin'));
    $this->dbManager->insertTableRow('license_ref', array('rf_pk' => 100, 'rf_shortname' => 'MIT'));
    $this->dbManager->insertTableRow('license_ref', array('rf_pk' => 101, 'rf_shortname' => 'GPL-2.0'));

    // Set a policy
    $this->policyDao->setLicensePolicy(100, 0, 1, 'Unit Test', '127.0.0.1'); // MIT -> Approved (0)
    $this->policyDao->setLicensePolicy(101, 2, 1, 'Unit Test', '127.0.0.1'); // GPL-2.0 -> Banned (2)
    
    // Test getAllPolicies
    $policies = $this->policyDao->getAllPolicies();
    $this->assertCount(2, $policies);
    
    $mitPolicy = null;
    $gplPolicy = null;
    foreach($policies as $p) {
        if ($p['rf_shortname'] === 'MIT') $mitPolicy = $p;
        if ($p['rf_shortname'] === 'GPL-2.0') $gplPolicy = $p;
    }
    
    $this->assertEquals(0, $mitPolicy['policy_rank']);
    $this->assertEquals(2, $gplPolicy['policy_rank']);
    
    // Audit logs test
    $logs = $this->dbManager->getRows("SELECT * FROM license_policy_log");
    $this->assertCount(2, $logs);
    $this->assertEquals('Unit Test', $logs[0]['source']);
    $this->assertEquals('127.0.0.1', $logs[0]['request_ip']);
  }

  public function testUpdatePolicyOverwritesAndLogs()
  {
    $this->dbManager->insertTableRow('users', array('user_pk' => 1, 'user_name' => 'admin'));
    $this->dbManager->insertTableRow('license_ref', array('rf_pk' => 100, 'rf_shortname' => 'MIT'));

    // Insert original
    $this->policyDao->setLicensePolicy(100, 1, 1, 'Unit Test', '127.0.0.1');
    
    // Overwrite to 0
    $this->policyDao->setLicensePolicy(100, 0, 1, 'Unit Test', '127.0.0.1');

    $policies = $this->policyDao->getAllPolicies();
    $this->assertCount(1, $policies);
    $this->assertEquals(0, $policies[0]['policy_rank']);
    
    $logs = $this->dbManager->getRows("SELECT * FROM license_policy_log ORDER BY id");
    $this->assertCount(2, $logs);
    
    // Verify first insert log
    $this->assertEquals(-1, $logs[0]['old_rank']);
    $this->assertEquals(1, $logs[0]['new_rank']);
    
    // Verify update log
    $this->assertEquals(1, $logs[1]['old_rank']);
    $this->assertEquals(0, $logs[1]['new_rank']);
  }

  public function testDeletePolicy()
  {
    $this->dbManager->insertTableRow('users', array('user_pk' => 1, 'user_name' => 'admin'));
    $this->dbManager->insertTableRow('license_ref', array('rf_pk' => 100, 'rf_shortname' => 'MIT'));
    
    // Insert
    $this->policyDao->setLicensePolicy(100, 0, 1, 'Unit Test', '127.0.0.1');
    $this->assertCount(1, $this->policyDao->getAllPolicies());
    
    // Delete
    $result = $this->policyDao->deleteLicensePolicy(100, 1, 'Unit Test', '127.0.0.1');
    $this->assertTrue($result);
    $this->assertCount(0, $this->policyDao->getAllPolicies());
    
    // Audit logs should be 2 (1 insert, 1 delete)
    $logs = $this->dbManager->getRows("SELECT * FROM license_policy_log ORDER BY id");
    $this->assertCount(2, $logs);
    $this->assertEquals(0, $logs[1]['old_rank']);
    $this->assertEquals(-1, $logs[1]['new_rank']); // Deleted
  }

  public function testInvalidRankConstraint()
  {
    $this->dbManager->insertTableRow('users', array('user_pk' => 1, 'user_name' => 'admin'));
    $this->dbManager->insertTableRow('license_ref', array('rf_pk' => 100, 'rf_shortname' => 'MIT'));
    
    $this->expectException(Exception::class);
    // Invalid rank 5 should trigger DB CHECK constraint exception
    $this->dbManager->insertTableRow('license_policy', array('rf_fk' => 100, 'policy_rank' => 5));
  }

  public function testPolicyFilter()
  {
    $this->dbManager->insertTableRow('users', array('user_pk' => 1, 'user_name' => 'admin'));
    // DB default is NULL or empty
    
    $this->assertEquals([], $this->policyDao->getPolicyFilter(1));
    
    $this->policyDao->setPolicyFilter(1, [0, 2]);
    $this->assertEquals([0, 2], $this->policyDao->getPolicyFilter(1));
    
    // Test invalid filters are stripped
    $this->policyDao->setPolicyFilter(1, [1, 9, 2]);
    $this->assertEquals([1, 2], $this->policyDao->getPolicyFilter(1));
  }
}
