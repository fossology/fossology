<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Monolog\Logger;

/**
 * @var string $SPDX_TEST_DATA
 * Location of test data from SPDX for license conclusions
 */
define("SPDX_TEST_DATA", dirname(dirname(dirname(dirname(__DIR__)))) .
  "/spdx/agent_tests/Functional/fo_report.sql");

/**
 * @class PfileDaoTest
 * @brief Test cases for PfileDao
 */
class PfileDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb $testDb
   * Test Db */
  private $testDb;
  /** @var DbManager $dbManager
   * DbManager */
  private $dbManager;
  /** @var Logger $logger
   * Logger */
  private $logger;
  /** @var PfileDao $pfileDao
   * Pfile dao */
  private $pfileDao;
  /** @var integer $assertCountBefore
   * Mock asserts */
  private $assertCountBefore;

  /**
   * Setup test DB and other objects
   * @see PHPUnit::Framework::TestCase::setUp()
   */
  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("pfiledao");
    $this->dbManager = $this->testDb->getDbManager();
    $this->logger = new Logger("test");
    $this->pfileDao = new PfileDao($this->dbManager, $this->logger);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  /**
   * Tear down test DB and objects
   * @see PHPUnit::Framework::TestCase::tearDown()
   */
  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount() -
      $this->assertCountBefore);
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
  }

  /**
   * @test
   * -# Test for PfileDao::getPfile()
   * -# Fetch multiple pfiles by various input combinations of checksums
   * -# Also, the case of checksum should not effect the results
   * -# Unavailable pfiles should be returned as null
   */
  public function testGetPfile()
  {
    $this->testDb->createPlainTables(['pfile']);
    $this->testDb->insertData(['pfile']);

    $pfileSha1 = [
      'pfile_pk'     => 755,
      'pfile_md5'    => 'E7295A5773D0EA17D53CBE6293924DD4',
      'pfile_sha1'   => '93247C8DB814F0A224B75B522C1FA4DC92DC3078',
      'pfile_sha256' => 'E29ABC32DB8B6241D598BC7C76681A7623D176D85F99E738A56C0CB684C367E1',
      'pfile_size'   => 10240,
      'pfile_mimetypefk' => 12
    ];
    $pfileMd5 = [
      'pfile_pk'     => 3,
      'pfile_md5'    => 'E35086757EE4F4B35B0B1C63107DE47F',
      'pfile_sha1'   => '678C8AE6FC318346D61D540D30AA4ACE024BBB8B',
      'pfile_sha256' => null,
      'pfile_size'   => 4483,
      'pfile_mimetypefk' => 30
    ];
    $pfileSha1Sha256 = [
      'pfile_pk'     => 758,
      'pfile_md5'    => '95405535F6607638FC8B344F369D8CA9',
      'pfile_sha1'   => 'FBE3181FD0ADDBD6E64B1FF6CAE1223A7DACB836',
      'pfile_sha256' => '77949BC4E251CF4F0BAD9C5DC8C44C5803BC50A7E73F3B5DCAF985DEBE0E93B4',
      'pfile_size'   => 64,
      'pfile_mimetypefk' => null
    ];
    $notFoundPfile = null;

    $validPfileSha1 = $this->pfileDao
      ->getPfile("93247C8DB814F0A224B75B522C1FA4DC92DC3078");
    $validPfileMd5 = $this->pfileDao
      ->getPfile(null, "e35086757ee4f4b35b0b1c63107de47f");
    $validPfileSha1Sha256 = $this->pfileDao
      ->getPfile("FBE3181FD0ADDBD6E64B1FF6CAE1223A7DACB836", null,
        "77949bc4e251cf4f0bad9c5dc8c44c5803bc50a7e73f3b5dcaf985debe0e93b4");
    $invalidPfileSha1 = $this->pfileDao
      ->getPfile("E47AA02935A589FADA86489705E0E0F0");

    $this->assertEquals($pfileSha1, $validPfileSha1);
    $this->assertEquals($pfileMd5, $validPfileMd5);
    $this->assertEquals($pfileSha1Sha256, $validPfileSha1Sha256);
    $this->assertEquals($notFoundPfile, $invalidPfileSha1);
  }

  /**
   * @test
   * -# Test for PfileDao::getScannerFindings()
   * -# Setup required tables and data
   * -# Fetch results for different pfiles with 1 or 2 results
   * -# Make sure the license list is sorted
   * -# Not found results should be empty array
   */
  public function testGetScannerFindings()
  {
    $this->testDb->createPlainTables(['license_ref', 'license_file']);
    $this->testDb->insertData(['license_ref', 'license_file']);

    $expectedFirstFinding = [
      'GPL-2.0-only',
      'LGPL-2.1-or-later'
    ];
    $expectedSecondFinding = [
      'Classpath-exception-2.0',
      'GPL-2.0-only'
    ];
    $expectedThirdFinding = [
      'MIT'
    ];
    $expectedNoFinding = [];

    $actualFirstFinding = $this->pfileDao->getScannerFindings(11);
    $actualSecondFinding = $this->pfileDao->getScannerFindings(14);
    $actualThirdFinding = $this->pfileDao->getScannerFindings(15);
    $actualNoFinding = $this->pfileDao->getScannerFindings(12);

    $this->assertEquals($expectedFirstFinding, $actualFirstFinding);
    $this->assertEquals($expectedSecondFinding, $actualSecondFinding);
    $this->assertEquals($expectedThirdFinding, $actualThirdFinding);
    $this->assertEquals($expectedNoFinding, $actualNoFinding);
  }

  /**
   * @test
   * -# Test for PfileDao::getUploadForPackage()
   * -# Check if corrects uploads are returned
   * -# Not found result should be null
   */
  public function testGetUploadForPackage()
  {
    $this->testDb->createPlainTables(['upload']);
    $this->testDb->insertData(['upload']);

    $expectedSingleUpload = [1];
    $expectedMultipleUploads = [2, 3];
    $expectedNoUpload = null;

    $actualSingleUpload = $this->pfileDao->getUploadForPackage(1);
    $actualMultipleUploads = $this->pfileDao->getUploadForPackage(9);
    $actualNoUpload = $this->pfileDao->getUploadForPackage(99);

    $this->assertEquals($expectedSingleUpload, $actualSingleUpload);
    $this->assertEquals($expectedMultipleUploads, $actualMultipleUploads);
    $this->assertEquals($expectedNoUpload, $actualNoUpload);
  }

  /**
   * @test
   * -# Test for PfileDao::getConclusions()
   * -# Setup test data from SPDX test data
   * -# Get conclusion for pfile with given group
   * -# Get conclusion for pfile with different group for global decisions
   * -# Not found result should be empty array
   */
  public function testGetConclusions()
  {
    $this->testDb->createPlainTables(['clearing_decision',
      'clearing_decision_event', 'clearing_event', 'license_ref']);
    $this->testDb->insertData(['clearing_decision', 'clearing_decision_event',
      'clearing_event', 'license_ref'], false, SPDX_TEST_DATA);

    $expectedLocalConclusion = ['lic A'];
    $expectedGlobalConclusion = ['lic B'];
    $expectedAllRemoval = ["NONE"];
    $expectedNoConclusion = ["NOASSERTION"];

    $actualLocalConclusion = $this->pfileDao->getConclusions(2, 2);
    $actualGlobalConclusion = $this->pfileDao->getConclusions(4, 3);
    $actualNoConclusions = $this->pfileDao->getConclusions(2, 7);
    $actualAllRemoval = $this->pfileDao->getConclusions(2, 6);

    $this->assertEquals($expectedLocalConclusion, $actualLocalConclusion);
    $this->assertEquals($expectedGlobalConclusion, $actualGlobalConclusion);
    $this->assertEquals($expectedNoConclusion, $actualNoConclusions);
    $this->assertEquals($expectedAllRemoval, $actualAllRemoval);
  }

  /**
   * @test
   * -# Test for PfileDao::getCopyright()
   * -# Setup required tables and data
   * -# Fetch results for different pfiles with 1 or 2 results
   * -# Make sure the license list is sorted
   * -# Not found results should be empty array
   */
  public function testGetCopyright()
  {
    $this->testDb->createPlainTables(['copyright', 'copyright_decision']);
    $this->testDb->insertData(['copyright', 'copyright_decision']);

    $expectedFirstFinding = [
      'copyright (c) 2048',
      'copyright (c) manual'
    ];
    $expectedSecondFinding = [
      'copyright (c) 2002 lawrence e. rosen. all rights
reserved. permission is hereby granted to copy and distribute this
',
      'copyright grant to the software. the
    bsd and apache licenses are vague and incomplete in that respect.
',
      'copyright to the license will control changes. the apache
'
    ];
    $expectedThirdFinding = [
      'copyright (c) independent manual'
    ];
    $expectedNoFinding = [];

    $actualFirstFinding = $this->pfileDao->getCopyright(9);
    $actualSecondFinding = $this->pfileDao->getCopyright(6);
    $actualThirdFinding = $this->pfileDao->getCopyright(14);
    $actualNoFinding = $this->pfileDao->getCopyright(15);

    $this->assertEquals($expectedFirstFinding, $actualFirstFinding);
    $this->assertEquals($expectedSecondFinding, $actualSecondFinding);
    $this->assertEquals($expectedThirdFinding, $actualThirdFinding);
    $this->assertEquals($expectedNoFinding, $actualNoFinding);
  }
}
