<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

use Exception;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\Reflectory;
use Fossology\Lib\Test\TestLiteDb;
use Mockery as M;

/**
 * @class LicenseCsvImportTest
 * @brief Test for LicenseCsvImport
 */
class LicenseCsvImportTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @brief One time setup for test
   * @see PHPUnit::Framework::TestCase::setUp()
   */
  protected function setUp() : void
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  /**
   * @brief Close mockery
   * @see PHPUnit::Framework::TestCase::tearDown()
   */
  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    M::close();
  }

  /**
   * @brief Test for LicenseCsvImport::getKeyFromShortname()
   * @test
   * -# Create test DB and insert a license in `license_ref`.
   * -# Call LicenseCsvImport::getKeyFromShortname() on a known license.
   * -# Check if the id matches.
   * -# Call LicenseCsvImport::getKeyFromShortname() on an unknown license.
   * -# Check if the return value is false.
   */
  public function testGetKeyFromShortname()
  {
    $testDb = new TestLiteDb();
    $testDb->createPlainTables(array('license_ref'));
    $shortname = 'licA';
    $knownId = 101;
    $knownGroup = 4;
    /*** @var DbManager ***/
    $dbManager = &$testDb->getDbManager();
    $dbManager->insertTableRow('license_ref', array('rf_pk'=>$knownId,'rf_shortname'=>$shortname));
    $this->createCandidateTable($dbManager);
    $dbManager->insertTableRow('license_candidate', array(
      'rf_pk' => $knownId + 2,
      'rf_shortname' => "candidate-$shortname",
      'group_fk' => $knownGroup
    ));
    $userDao = M::mock(UserDao::class);
    $userDao->shouldReceive('getGroupIdByName')
      ->with("fossy")
      ->once()
      ->andReturn(4);
    $licenseCsvImport = new LicenseCsvImport($dbManager, $userDao);

    assertThat(Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,'getKeyFromShortname', array($shortname)), equalTo($knownId));
    assertThat(Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,'getKeyFromShortname', array("no $shortname")), equalTo(false));
    // Candidates
    assertThat(Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,
      'getKeyFromShortname',
      array("candidate-$shortname", "fossy")),
      equalTo($knownId + 2));
    assertThat(Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,
      'getKeyFromShortname',
      array("candidate-$shortname")),
      equalTo(false));
  }

  /**
   * @brief Test for LicenseCsvImport::getKeyFromMd5()
   * @test
   * -# Create test DB and insert a license in `license_ref`.
   * -# Call LicenseCsvImport::getKeyFromMd5() on a known license.
   * -# Check if the id matches.
   * -# Call LicenseCsvImport::getKeyFromMd5() on an unknown license.
   * -# Check if the return value is false.
   */
  public function testGetKeyFromMd5()
  {
    $licenseText = 'I am a strong license';
    $knownId = 101;
    $falseLicenseText = "I am a weak license";

    $dbManager = M::mock(DbManager::class);
    $dbManager->shouldReceive('getSingleRow')
      ->with('SELECT rf_pk FROM ONLY license_ref WHERE rf_md5=md5($1)',
        array($licenseText))
      ->once()
      ->andReturn(array('rf_pk' => $knownId));
    $dbManager->shouldReceive('getSingleRow')
      ->with('SELECT rf_pk FROM ONLY license_ref WHERE rf_md5=md5($1)',
        array($falseLicenseText))
      ->andReturnNull();
    $userDao = M::mock(UserDao::class);
    $licenseCsvImport = new LicenseCsvImport($dbManager, $userDao);

    assertThat(Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,
      'getKeyFromMd5', array($licenseText)), equalTo($knownId));
    assertThat(Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,
      'getKeyFromMd5', array($falseLicenseText)), equalTo(false));
  }

  /**
   * @brief Test for LicenseCsvImport::handleCsvLicense()
   * @test
   * -# Mock DB manager object.
   * -# Create object of LicenseCsvImport and initialize nkMap.
   * -# Call several handle calls and check the log messages.
   */
  public function testHandleCsvLicense()
  {
    $dbManager = M::mock(DbManager::class);
    $nkMap = array(
      'licA' => 101,
      'licB' => false,
      'licC' => false,
      'licE' => false,
      'licF' => false,
      'licG' => false,
      'licH' => false,
      'licZ' => 100,
      'canLicAfossy' => 200,
      'canLicBfossy' => false
    );
    $mdkMap = array(
      md5('txA') => 101,
      md5('txB') => false,
      md5('txC') => false,
      md5('txD') => 102,
      md5('txE') => false,
      md5('txF') => false,
      md5('txG') => false,
      md5('txH') => false,
      md5('txZ') => 100,
      md5('txCan') => 200,
      md5('Text of candidate license') => false
    );
    $userDao = M::mock(UserDao::class);
    $userDao->shouldReceive('getGroupIdByName')
      ->with("fossy")
      ->times(3)
      ->andReturn(4);

    $licenseCsvImport = new LicenseCsvImport($dbManager, $userDao);
    Reflectory::setObjectsProperty($licenseCsvImport, 'nkMap', $nkMap);
    Reflectory::setObjectsProperty($licenseCsvImport, 'mdkMap', $mdkMap);

    $singleRowA = array(
      'rf_shortname' => 'licA',
      'rf_spdx_id' => 'lrf-licA',
      'rf_licensetype' => 'lictypeA',
      'rf_fullname' => 'licennnseA',
      'rf_text' => 'someRandom',
      'rf_md5' => md5('someRandom'),
      'rf_detector_type' => 1,
      'rf_url' => '',
      'rf_notes' => '',
      'rf_source' => '',
      'rf_risk' => 4
    );
    $dbManager->shouldReceive('getSingleRow')
      ->with(
      'SELECT rf_shortname, rf_licensetype, rf_fullname, rf_spdx_id, rf_text, rf_url, rf_notes, rf_source, rf_risk ' .
      'FROM license_ref WHERE rf_pk = $1', array(101), anything())
      ->once()
      ->andReturn($singleRowA);

    // Test for licB insert
    $dbManager->shouldReceive('insertTableRow')
      ->withArgs(array(
        'license_map', array(
          'rf_fk' => 103, 'rf_parent' => 101, 'usage' => LicenseMap::CONCLUSION
      )))
      ->once();
    $singleRowB = $singleRowA;
    $singleRowB["rf_shortname"] = "licB";
    $singleRowB["rf_licensetype"] = "lictypeB";
    $singleRowB["rf_fullname"] = "liceB";
    $singleRowB["rf_spdx_id"] = "lrf-B";
    $singleRowB["rf_text"] = "txB";
    $singleRowB["rf_md5"] = md5("txB");
    $singleRowB["rf_risk"] = 0;
    $this->addLicenseInsertToDbManager($dbManager, $singleRowB, 103);
    $returnB = Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,
      'handleCsvLicense', array(array(
        'shortname' => 'licB',
        'spdx_id' => 'lrf-B',
        'licensetype' => 'lictypeB',
        'fullname' => 'liceB',
        'text' => 'txB',
        'url' => '',
        'notes' => '',
        'source' => '',
        'risk' => 0,
        'parent_shortname' => 'licA',
        'report_shortname' => null,
        'group' => null
      )));
    assertThat($returnB, is("Inserted 'licB' in DB with conclusion 'licA'"));

    // Test for licF insert
    $singleRowF = $singleRowA;
    $singleRowF["rf_shortname"] = "licF";
    $singleRowF["rf_licensetype"] = "lictypeF";
    $singleRowF["rf_fullname"] = "liceF";
    $singleRowF["rf_spdx_id"] = null;
    $singleRowF["rf_text"] = "txF";
    $singleRowF["rf_md5"] = md5("txF");
    $singleRowF["rf_risk"] = 1;
    $this->addLicenseInsertToDbManager($dbManager, $singleRowF, 104);
    $dbManager->shouldReceive('insertTableRow')
      ->withArgs(array(
        'license_map', array(
          'rf_fk' => 104,
          'rf_parent' => 100,
          'usage' => LicenseMap::REPORT
      )))
      ->once();
    $returnF = Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,
      'handleCsvLicense', array(array(
        'shortname' => 'licF',
        'licensetype' => 'lictypeF',
        'fullname' => 'liceF',
        'spdx_id' => null,
        'text' => 'txF',
        'url' => '',
        'notes' => '',
        'source' => '',
        'risk' => 1,
        'parent_shortname' => null,
        'report_shortname' => 'licZ',
        'group' => null
      )));
    assertThat($returnF, is("Inserted 'licF' in DB reporting 'licZ'"));

    // Test licC insert
    $singleRowC = $singleRowA;
    $singleRowC["rf_shortname"] = "licC";
    $singleRowC["rf_licensetype"] = "lictypeC";
    $singleRowC["rf_fullname"] = "liceC";
    $singleRowC["rf_spdx_id"] = "lrf-licC";
    $singleRowC["rf_text"] = "txC";
    $singleRowC["rf_md5"] = md5("txC");
    $singleRowC["rf_risk"] = 2;
    $this->addLicenseInsertToDbManager($dbManager, $singleRowC, 105);
    $returnC = Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,
      'handleCsvLicense', array(array(
        'shortname' => 'licC',
        'licensetype' => 'lictypeC',
        'fullname' => 'liceC',
        'spdx_id' => 'lrf-licC',
        'text' => 'txC',
        'url' => '',
        'notes' => '',
        'source' => '',
        'risk' => 2,
        'parent_shortname' => null,
        'report_shortname' => null,
        'group' => null
      )));
    assertThat($returnC, is("Inserted 'licC' in DB"));

    // Test canlicC update
    $canLicA = $singleRowA;
    $canLicA["rf_shortname"] = "canLicA";
    $canLicA["rf_licensetype"] = "canLicTypeA";
    $canLicA["rf_fullname"] = "canLiceA";
    $canLicA["rf_spdx_id"] = null;
    $canLicA["rf_text"] = "txcan";
    $canLicA["rf_risk"] = 0;
    $canLicA["rf_group"] = 4;
    $dbManager->shouldReceive('getSingleRow')
    ->with(
      'SELECT rf_shortname, rf_licensetype, rf_fullname, rf_spdx_id, rf_text, rf_url, rf_notes, rf_source, rf_risk ' .
      'FROM license_ref WHERE rf_pk = $1', array(200), anything())
      ->once()
      ->andReturn($canLicA);
    $dbManager->shouldReceive('getSingleRow')
      ->with(
        "UPDATE license_candidate SET " .
        "rf_fullname=$2,rf_spdx_id=$3,rf_text=$4,rf_md5=md5($4) WHERE rf_pk=$1;",
        array(200, 'canDidateLicenseA', 'lrf-canLicA', 'Text of candidate license'),
        anything())
      ->once();
    $dbManager->shouldReceive('getSingleRow')
      ->with(
        'SELECT rf_parent FROM license_map WHERE rf_fk = $1 AND usage = $2;',
        anyof(array(200, LicenseMap::CONCLUSION), array(200, LicenseMap::REPORT)),
        anything())
        ->twice()
        ->andReturn(array('rf_parent' => null));
    $returnC = Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,
      'handleCsvLicense', array(array(
        'shortname' => 'canLicA',
        'licensetype' => 'canLicTypeA',
        'fullname' => 'canDidateLicenseA',
        'spdx_id' => 'lrf-canLicA',
        'text' => 'Text of candidate license',
        'url' => '', 'notes' => '', 'source' => '', 'risk' => 0,
        'parent_shortname' => null, 'report_shortname' => null,
        'group' => 'fossy'
      )));
    assertThat($returnC, is(
      "License 'canLicA' already exists in DB (id = 200)" .
      ", updated fullname, updated SPDX ID, updated text"
    ));

    // Test licA update
    $dbManager->shouldReceive('getSingleRow')
      ->with(
      "UPDATE license_ref SET " .
      "rf_fullname=$2,rf_text=$3,rf_md5=md5($3),rf_risk=$4 WHERE rf_pk=$1;",
      array(101, 'liceB', 'txA', 2), anything())
      ->once();
    $dbManager->shouldReceive('getSingleRow')
      ->with(
        'SELECT rf_parent FROM license_map WHERE rf_fk = $1 AND usage = $2;',
        anyof(array(101, LicenseMap::CONCLUSION), array(101, LicenseMap::REPORT)),
        anything())
      ->twice()
      ->andReturn(array('rf_parent' => null));
    $returnA = Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,
      'handleCsvLicense', array(array(
        'shortname' => 'licA',
        'licensetype' => 'lictypeA',
        'fullname' => 'liceB',
        'text' => 'txA',
        'url' => '',
        'notes' => '',
        'source' => '',
        'risk' => 2,
        'parent_shortname' => null,
        'report_shortname' => null,
        'group' => null
      )));
    assertThat($returnA, is(
        "License 'licA' already exists in DB (id = 101)" .
        ", updated fullname, updated text, updated the risk level"));

    // Test licE md5 collision
    $returnE = Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,
      'handleCsvLicense', array(array(
        'shortname' => 'licE',
        'licensetype' => 'lictypeE',
        'fullname' => 'liceE',
        'text' => 'txD',
        'url' => '',
        'notes' => '',
        'source' => '',
        'risk' => false,
        'parent_shortname' => null,
        'report_shortname' => null,
        'group' => null
      )));
    assertThat($returnE, is(
      "Error: MD5 checksum of 'licE' collides with license id=102"));

    // Test licG md5 collision
    $returnG = Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,
      'handleCsvLicense', array(array(
        'shortname' => 'licG',
        'licensetype' => 'lictypeG',
        'fullname' => 'liceG',
        'text' => 'txD',
        'url' => '',
        'notes' => '',
        'source' => '_G_go_G_',
        'parent_shortname' => null,
        'report_shortname' => null,
        'risk' => false,
        'group' => null
      )));
    assertThat($returnG, is(
      "Error: MD5 checksum of 'licG' collides with license id=102"));

    // Test canlicB insert
    $canlicB = $singleRowA;
    $canlicB["rf_shortname"] = "canLicB";
    $canlicB["rf_licensetype"] = "canLicTypeB";
    $canlicB["rf_fullname"] = "canLiceB";
    $canlicB["rf_spdx_id"] = null;
    $canlicB["rf_text"] = "txCan";
    $canlicB["rf_md5"] = md5("txCan");
    $canlicB["rf_risk"] = 2;
    $canlicB["group_fk"] = 4;
    $canlicB["marydone"] = 't';
    $this->addLicenseInsertToDbManager($dbManager, $canlicB, 201,
      "license_candidate");
    $dbManager->shouldReceive('booleanToDb')
      ->with(true)
      ->once()
      ->andReturn('t');
    $returnC = Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,
      'handleCsvLicense', array(array(
        'shortname' => 'canLicB',
        'licensetype' => 'canLicTypeB',
        'fullname' => 'canLiceB',
        'spdx_id' => null,
        'text' => 'txCan',
        'url' => '',
        'notes' => '',
        'source' => '',
        'risk' => 2,
        'parent_shortname' => null,
        'report_shortname' => null,
        'group' => 'fossy'
      )));
    assertThat($returnC, is("Inserted 'canLicB' in DB" .
      " as candidate license under group fossy"));
  }

  /**
   *
   * @brief Test for LicenseCsvImport::handleHeadCsv()
   * @test
   * -# Initialize LicenseCsvImport.
   * -# Call LicenseCsvImport::handleHeadCsv() on actual header names.
   * -# Check if the header returned have required keys.
   * -# Call LicenseCsvImport::handleHeadCsv() on alias header names.
   * -# Check if the header returned have required keys.
   */
  public function testHandleHeadCsv()
  {
    $dbManager = M::mock(DbManager::class);
    $userDao = M::mock(UserDao::class);
    $licenseCsvImport = new LicenseCsvImport($dbManager, $userDao);

    assertThat(
      Reflectory::invokeObjectsMethodnameWith($licenseCsvImport, 'handleHeadCsv',
        array(array(
          'shortname', 'foo', 'text', 'fullname', 'notes', 'bar', 'spdx_id', 'licensetype'
        ))),
      is(array(
        'shortname' => 0, 'fullname' => 3, 'text' => 2, 'spdx_id' => 6,
        'parent_shortname' => false, 'report_shortname' => false,
        'url' => false, 'notes' => 4, 'source' => false, 'risk' => 0,
        'group' => false,'licensetype' => 6
      )));

    assertThat(
      Reflectory::invokeObjectsMethodnameWith($licenseCsvImport, 'handleHeadCsv',
        array(array(
          'Short Name', 'URL', 'text', 'fullname', 'notes', 'Foreign ID', 'SPDX ID',
          'License group', 'License Type'
        ))),
      is(array(
        'shortname' => 0, 'fullname' => 3, 'spdx_id' => 6, 'text' => 2,
        'parent_shortname' => false, 'report_shortname' => false, 'url' => 1,
        'notes' => 4, 'source' => 5, 'risk' => false, 'group' => 7, 'licensetype' => 8
      )));
  }

  /**
   * @brief Test for LicenseCsvImport::handleHeadCsv()
   * @test
   * -# Initialize LicenseCsvImport.
   * -# Call LicenseCsvImport::handleHeadCsv() on missing header names.
   * -# Function must throw an Exception.
   */
  public function testHandleHeadCsv_missingMandidatoryKey()
  {
    $this->expectException(Exception::class);
    $dbManager = M::mock(DbManager::class);
    $userDao = M::mock(UserDao::class);
    $licenseCsvImport = new LicenseCsvImport($dbManager, $userDao);
    Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,'handleHeadCsv',array(array('shortname','foo','text')));
  }

  /**
   * @brief Test for LicenseCsvImport::setDelimiter()
   * @test
   * -# Initialize LicenseCsvImport.
   * -# Set a new delimiter using LicenseCsvImport::setDelimiter().
   * -# Check if the delimiter is changed.
   * -# Set a new delimiter using LicenseCsvImport::setDelimiter().
   * -# Check if the delimiter is changed with only the first character passed.
   */
  public function testSetDelimiter()
  {
    $dbManager = M::mock(DbManager::class);
    $userDao = M::mock(UserDao::class);
    $licenseCsvImport = new LicenseCsvImport($dbManager, $userDao);

    $licenseCsvImport->setDelimiter('|');
    assertThat(Reflectory::getObjectsProperty($licenseCsvImport,'delimiter'),is('|'));

    $licenseCsvImport->setDelimiter('<>');
    assertThat(Reflectory::getObjectsProperty($licenseCsvImport,'delimiter'),is('<'));
  }

  /**
   * @brief Test for LicenseCsvImport::setEnclosure()
   * @test
   * -# Initialize LicenseCsvImport.
   * -# Set a new enclosure using LicenseCsvImport::setEnclosure().
   * -# Check if the enclosure is changed.
   * -# Set a new enclosure using LicenseCsvImport::setEnclosure().
   * -# Check if the enclosure is changed with only the first character passed.
   */
  public function testSetEnclosure()
  {
    $dbManager = M::mock(DbManager::class);
    $userDao = M::mock(UserDao::class);
    $licenseCsvImport = new LicenseCsvImport($dbManager, $userDao);

    $licenseCsvImport->setEnclosure('|');
    assertThat(Reflectory::getObjectsProperty($licenseCsvImport,'enclosure') ,is('|'));

    $licenseCsvImport->setEnclosure('<>');
    assertThat(Reflectory::getObjectsProperty($licenseCsvImport,'enclosure'),is('<'));
  }

  /**
   * @brief Test for LicenseCsvImport::handleCsv()
   * @test
   * -# Call LicenseCsvImport::handleCsv() for first time. headrow must be set.
   * -# Call LicenseCsvImport::handleCsv() with sample data.
   * -# Check if it is imported in nkMap
   */
  public function testHandleCsv()
  {
    $dbManager = M::mock(DbManager::class);
    $userDao = M::mock(UserDao::class);
    $licenseCsvImport = new LicenseCsvImport($dbManager, $userDao);

    Reflectory::invokeObjectsMethodnameWith($licenseCsvImport, 'handleCsv',
      array(array('shortname','licensetype', 'foo', 'text', 'fullname', 'notes', 'spdx_id')));
    assertThat(Reflectory::getObjectsProperty($licenseCsvImport, 'headrow'),
      is(notNullValue()));

    $dbManager->shouldReceive('getSingleRow')
      ->with(
        'SELECT rf_shortname,rf_source,rf_pk,rf_risk FROM license_ref WHERE rf_md5=md5($1)',
        anything())
      ->andReturn(false);
    $licenseRow = array(
      "rf_shortname" => "licA",
      "rf_licensetype" => "lictypeA",
      "rf_fullname" => "liceA",
      "rf_spdx_id" => null,
      "rf_text" => "txA",
      "rf_md5" => md5("txA"),
      "rf_detector_type" => 1,
      "rf_url" => '',
      "rf_notes" => 'noteA',
      "rf_source" => '',
      "rf_risk" => 0
    );
    $this->addLicenseInsertToDbManager($dbManager, $licenseRow, 101);
    Reflectory::setObjectsProperty($licenseCsvImport, 'nkMap', array(
        'licA' => false
    ));
    Reflectory::setObjectsProperty($licenseCsvImport, 'mdkMap', array(
        md5('txA') => false
    ));
    Reflectory::invokeObjectsMethodnameWith($licenseCsvImport, 'handleCsv',
      array(array('licA', 'lictypeA', 'bar', 'txA', 'liceA', 'noteA')));
    assertThat(Reflectory::getObjectsProperty($licenseCsvImport, 'nkMap'),
      is(array('licA' => 101)));
    assertThat(Reflectory::getObjectsProperty($licenseCsvImport, 'mdkMap'),
      is(array(md5('txA') => 101)));
  }

  /**
   * @brief Test for LicenseCsvImport::handleFile() (non-existing file)
   * @test
   * -# Call LicenseCsvImport::handleFile() on non-existing file
   * -# Function must return `Internal error`
   */
  public function testHandleFileIfFileNotExists()
  {
    $dbManager = M::mock(DbManager::class);
    $userDao = M::mock(UserDao::class);
    $licenseCsvImport = new LicenseCsvImport($dbManager, $userDao);
    $msg = $licenseCsvImport->handleFile('/tmp/thisFileNameShouldNotExists', 'csv');
    assertThat($msg, is(equalTo(_('Internal error'))));
  }

  /**
   * @brief Test for LicenseCsvImport::handleFile() (non-csv file)
   * @test
   * -# Call LicenseCsvImport::handleFile() on non-csv file
   * -# Function must return `Error while parsing file`
   */
  public function testHandleFileIfFileIsNotParsable()
  {
    $dbManager = M::mock(DbManager::class);
    $userDao = M::mock(UserDao::class);
    $licenseCsvImport = new LicenseCsvImport($dbManager, $userDao);
    $msg = $licenseCsvImport->handleFile(__FILE__, 'csv');
    assertThat($msg, startsWith( _('Error while parsing file')));
  }

  /**
   * @brief Test for LicenseCsvImport::handleFile() (csv file with header)
   * @test
   * -# Create an empty CSV file and write a valid header.
   * -# Call LicenseCsvImport::handleFile().
   * -# Function must return message starting with `head okay`.
   */
  public function testHandleFile()
  {
    $dbManager = M::mock(DbManager::class);
    $userDao = M::mock(UserDao::class);
    $licenseCsvImport = new LicenseCsvImport($dbManager, $userDao);
    $filename = tempnam("/tmp", "FOO");
    $handle = fopen($filename, 'w');
    fwrite($handle, "shortname,fullname,text,spdx_id");
    fclose($handle);
    $msg = $licenseCsvImport->handleFile($filename, 'csv');
    assertThat($msg, startsWith( _('head okay')));
    unlink($filename);
  }

  /**
   * @brief Test for LicenseCsvImport::setMap()
   * @test
   * -# Create test DB and insert 3 licenses in `license_ref`.
   * -# Call LicenseCsvImport::setMap() with a reporting and conclusion mapping.
   * -# Check if return value is true and mapping is done in the DB.
   */
  public function testSetMapTrue()
  {
    $testDb = new TestLiteDb();
    $testDb->createPlainTables(array('license_ref', 'license_map'));
    $licenseId = 101;
    $parentId = 102;
    $reportId = 103;
    /** @var DbManager $dbManager **/
    $dbManager = &$testDb->getDbManager();
    $dbManager->insertTableRow('license_ref', array(
      'rf_pk' => $licenseId,
      'rf_shortname' => "Main License"
    ));
    $dbManager->insertTableRow('license_ref', array(
      'rf_pk' => $parentId,
      'rf_shortname' => "Parent License"
    ));
    $dbManager->insertTableRow('license_ref', array(
      'rf_pk' => $reportId,
      'rf_shortname' => "Reported License"
    ));
    $userDao = M::mock(UserDao::class);
    $licenseCsvImport = new LicenseCsvImport($dbManager, $userDao);

    assertThat(Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,
      'setMap', array($parentId, $licenseId, LicenseMap::CONCLUSION)),
      equalTo(true));
    assertThat(Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,
      'setMap', array($reportId, $licenseId, LicenseMap::REPORT)),
      equalTo(true));

    $sql = "SELECT rf_parent FROM license_map WHERE rf_fk = $1 AND usage = $2;";
    $statement = __METHOD__ . ".getMap";
    $row = $dbManager->getSingleRow($sql, array($licenseId,
      LicenseMap::CONCLUSION), $statement);

    assertThat($row['rf_parent'], equalTo($parentId));
    $row = $dbManager->getSingleRow($sql, array($licenseId,
      LicenseMap::REPORT), $statement);
    assertThat($row['rf_parent'], equalTo($reportId));
  }

  /**
   * @brief Test for LicenseCsvImport::setMap() with empty mapping
   * @test
   * -# Create test DB and insert 3 licenses in `license_ref`.
   * -# Call LicenseCsvImport::setMap() with a reporting and conclusion mapping
   *    but the conclusions should be worng licenses.
   * -# Check if return value is false and there is no mapping done in the DB.
   */
  public function testSetMapFalse()
  {
    $testDb = new TestLiteDb();
    $testDb->createPlainTables(array('license_ref', 'license_map'));
    $licenseId = 101;
    $parentId = false;
    $reportId = false;
    /** @var DbManager $dbManager **/
    $dbManager = &$testDb->getDbManager();
    $dbManager->insertTableRow('license_ref', array(
      'rf_pk' => $licenseId,
      'rf_shortname' => "Main License"
    ));
    $userDao = M::mock(UserDao::class);
    $licenseCsvImport = new LicenseCsvImport($dbManager, $userDao);

    assertThat(Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,
      'setMap', array($parentId, $licenseId, LicenseMap::CONCLUSION)),
      equalTo(false));
    assertThat(Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,
      'setMap', array($reportId, $licenseId, LicenseMap::REPORT)),
      equalTo(false));

    $sql = "SELECT rf_parent FROM license_map WHERE rf_fk = $1 AND usage = $2;";
    $statement = __METHOD__ . ".getMap";
    $row = $dbManager->getSingleRow($sql, array($licenseId,
      LicenseMap::CONCLUSION), $statement);

    assertThat($row, equalTo(false));
    $row = $dbManager->getSingleRow($sql, array($licenseId,
      LicenseMap::REPORT), $statement);
    assertThat($row, equalTo(false));
  }

  /**
   * Create candidate license table
   * @param DbManager $dbManager
   */
  private function createCandidateTable($dbManager)
  {
    $sql = "CREATE TABLE license_candidate (" .
      "rf_pk, rf_shortname, rf_fullname, rf_text, rf_md5, rf_url, rf_notes, " .
      "marydone, rf_source, rf_risk, rf_detector_type, group_fk)";
    $dbManager->queryOnce($sql);
  }

  /**
   * Add a new mockery handler for new license insertion in DB
   * @param DbManager $dbManager The mock object of DbManager
   * @param array $row    The associated array
   * @param mixed $return The value which should be returned
   * @param string $table The table where new data should go
   */
  private function addLicenseInsertToDbManager(&$dbManager, $row, $return,
    $table = "license_ref")
  {
    $dbManager->shouldReceive('insertTableRow')
      ->with($table, $row, anything(), 'rf_pk')
      ->once()
      ->andReturn($return);
  }
}
