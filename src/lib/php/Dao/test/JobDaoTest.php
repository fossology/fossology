<?php

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestLiteDb;
use Monolog\Logger;

class JobDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestLiteDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;
  /** @var JobDao */
  private $jobDao;
  /** @var assertCountBefore */
  private $assertCountBefore;

  protected function setUp() : void
  {
    $this->testDb = new TestLiteDb();
    $this->dbManager = $this->testDb->getDbManager();
    $this->logger = new Logger("test");
    $this->jobDao = new JobDao($this->dbManager, $this->logger);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb = null;
    $this->dbManager = null;
  }

  public function testHasActionPermissionsOnJob()
  {
    $this->testDb->createPlainTables(array('job', 'group_user_member'));

    $jobId = 1001;
    $ownerUserId = 501;
    $memberUserId = 777;
    $memberGroupId = 909;

    $this->dbManager->insertTableRow('job', array(
      'job_pk' => $jobId,
      'job_user_fk' => $ownerUserId
    ));

    $this->dbManager->insertTableRow('group_user_member', array(
      'group_fk' => $memberGroupId,
      'user_fk' => $ownerUserId
    ));

    assertThat($this->jobDao->hasActionPermissionsOnJob($jobId, $ownerUserId, 1), equalTo(true));
    assertThat($this->jobDao->hasActionPermissionsOnJob($jobId, $memberUserId, $memberGroupId), equalTo(true));
    assertThat($this->jobDao->hasActionPermissionsOnJob($jobId, $memberUserId, 1), equalTo(false));
  }
}
