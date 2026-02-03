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

class LicenseAcknowledgementDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var LicenseAcknowledgementDao */
  private $licenseAcknowledgementDao;
  /** @var integer */
  private $assertCountBefore;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = $this->testDb->getDbManager();

    $this->licenseAcknowledgementDao = new LicenseAcknowledgementDao($this->dbManager);

    $this->testDb->createPlainTables(array('license_std_acknowledgement'));

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

  public function testGetAllAcknowledgements()
  {
    $this->dbManager->insertTableRow('license_std_acknowledgement', array('name' => 'test1', 'acknowledgement' => 'ack1', 'is_enabled' => true));
    $acks = $this->licenseAcknowledgementDao->getAllAcknowledgements();
    assertThat(count($acks), is(1));
    assertThat($acks[0]['name'], is('test1'));
  }

  public function testGetAcknowledgement()
  {
    $id = $this->dbManager->insertTableRow('license_std_acknowledgement', array('name' => 'test1', 'acknowledgement' => 'ack1'), 'test', 'la_pk');
    $ack = $this->licenseAcknowledgementDao->getAcknowledgement($id);
    assertThat($ack, is('ack1'));
  }
}
