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

class SoftwareHeritageDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var SoftwareHeritageDao */
  private $softwareHeritageDao;
  /** @var integer */
  private $assertCountBefore;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = $this->testDb->getDbManager();
    $logger = new Logger("test");
    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');

    $this->softwareHeritageDao = new SoftwareHeritageDao($this->dbManager, $logger, $uploadDao);

    $this->testDb->createPlainTables(array('software_heritage'));

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

  public function testSetSoftwareHeritageDetails()
  {
    $res = $this->softwareHeritageDao->setSoftwareHeritageDetails(1, 'GPL-2.0', 200);
    assertThat($res, is(true));
    
    $record = $this->softwareHeritageDao->getSoftwareHetiageRecord(1);
    assertThat($record['license'], is('GPL-2.0'));
  }

  public function testGetSoftwareHetiageRecordNotFound()
  {
    $record = $this->softwareHeritageDao->getSoftwareHetiageRecord(999);
    assertThat($record['license'], is(nullValue()));
  }
}
