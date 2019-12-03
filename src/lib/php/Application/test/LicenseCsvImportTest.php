<?php
/*
Copyright (C) 2014-2015, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Exception;
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
  protected function setUp()
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  /**
   * @brief Close mockery
   * @see PHPUnit::Framework::TestCase::tearDown()
   */
  protected function tearDown()
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
    /*** @var DbManager ***/
    $dbManager = &$testDb->getDbManager();
    $dbManager->insertTableRow('license_ref', array('rf_pk'=>$knownId,'rf_shortname'=>$shortname));
    $licenseCsvImport = new LicenseCsvImport($dbManager);

    assertThat(Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,'getKeyFromShortname', array($shortname)), equalTo($knownId));
    assertThat(Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,'getKeyFromShortname', array("no $shortname")), equalTo(false));
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
    $licenseCsvImport = new LicenseCsvImport($dbManager);
    $nkMap = array('licA'=>101,'licB'=>false,'licC'=>false,'licE'=>false,'licF'=>false,'licG'=>false,'licH'=>false,'licZ'=>100);
    Reflectory::setObjectsProperty($licenseCsvImport, 'nkMap', $nkMap);

    $singleRowD = array('rf_shortname'=>'licD','rf_source'=>'','rf_pk'=>101+3,'rf_risk'=>4);
    $dbManager->shouldReceive('getSingleRow')
            ->with('SELECT rf_shortname,rf_source,rf_pk,rf_risk FROM license_ref WHERE rf_md5=md5($1)',anything())
            ->times(6)
            ->andReturn(false,false,false,$singleRowD,$singleRowD,$singleRowD);
    $dbManager->shouldReceive('prepare');
    $dbManager->shouldReceive('execute');
    $dbManager->shouldReceive('freeResult');
    $dbManager->shouldReceive('fetchArray')->andReturn(array('rf_pk'=>102));

    $dbManager->shouldReceive('insertTableRow')->withArgs(array('license_map',
        array('rf_fk'=>102,'rf_parent'=>101,'usage'=>LicenseMap::CONCLUSION)))->once();
    $returnB = Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,'handleCsvLicense', array(
            array('shortname'=>'licB','fullname'=>'liceB','text'=>'txB','url'=>'','notes'=>'','source'=>'','risk'=>0,
                'parent_shortname'=>'licA','report_shortname'=>null)));
    assertThat($returnB, is("Inserted 'licB' in DB with conclusion 'licA'"));

    $dbManager->shouldReceive('insertTableRow')->withArgs(array('license_map',
        array('rf_fk'=>102,'rf_parent'=>100,'usage'=>LicenseMap::REPORT)))->once();
    $returnF = Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,'handleCsvLicense', array(
            array('shortname'=>'licF','fullname'=>'liceF','text'=>'txF','url'=>'','notes'=>'','source'=>'','risk'=>1,
                'parent_shortname'=>null,'report_shortname'=>'licZ')));
    assertThat($returnF, is("Inserted 'licF' in DB reporting 'licZ'"));

    $returnC = Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,'handleCsvLicense', array(
            array('shortname'=>'licC','fullname'=>'liceC','text'=>'txC','url'=>'','notes'=>'','source'=>'','risk'=>2,
                'parent_shortname'=>null,'report_shortname'=>null)));
    assertThat($returnC, is("Inserted 'licC' in DB"));

    $returnA = Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,'handleCsvLicense', array(
            array('shortname'=>'licA','fullname'=>'liceB','text'=>'txB','url'=>'','notes'=>'','source'=>'','risk'=>2,
                'parent_shortname'=>null,'report_shortname'=>null)));
    assertThat($returnA, is("Shortname 'licA' already in DB (id=101)"));

    $returnE = Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,'handleCsvLicense', array(
            array('shortname'=>'licE','fullname'=>'liceE','text'=>'txD','url'=>'','notes'=>'','source'=>'','risk'=>false,
                'parent_shortname'=>null,'report_shortname'=>null)));
    assertThat($returnE, is("Text of 'licE' already used for 'licD'"));

    $returnG = Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,'handleCsvLicense', array(
            array('shortname'=>'licG','fullname'=>'liceG','text'=>'txD','url'=>'','notes'=>'','source'=>'_G_go_G_',
                'parent_shortname'=>null,'report_shortname'=>null,'risk'=>false)));
    assertThat($returnG, is("Text of 'licG' already used for 'licD', updated the source"));

    $returnH = Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,'handleCsvLicense', array(
            array('shortname'=>'licH','fullname'=>'liceH','text'=>'txD','url'=>'','notes'=>'','source'=>'_G_go_G_',
                'parent_shortname'=>null,'report_shortname'=>null,'risk'=>3)));
    assertThat($returnH, is("Text of 'licH' already used for 'licD', updated the source, updated the risk level"));
  }

  /**
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
    $licenseCsvImport = new LicenseCsvImport($dbManager);

    assertThat(Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,'handleHeadCsv',array(array('shortname','foo','text','fullname','notes','bar'))),
            is( array('shortname'=>0,'fullname'=>3,'text'=>2,'parent_shortname'=>false,'report_shortname'=>false,'url'=>false,'notes'=>4,'source'=>false,'risk'=>0) ) );

    assertThat(Reflectory::invokeObjectsMethodnameWith($licenseCsvImport,'handleHeadCsv',array(array('Short Name','URL','text','fullname','notes','Foreign ID'))),
            is( array('shortname'=>0,'fullname'=>3,'text'=>2,'parent_shortname'=>false,'report_shortname'=>false,'url'=>1,'notes'=>4,'source'=>5,'risk'=>false) ) );
  }

  /**
   * @expectedException Exception
   * @brief Test for LicenseCsvImport::handleHeadCsv()
   * @test
   * -# Initialize LicenseCsvImport.
   * -# Call LicenseCsvImport::handleHeadCsv() on missing header names.
   * -# Function must throw an Exception.
   */
  public function testHandleHeadCsv_missingMandidatoryKey()
  {
    $dbManager = M::mock(DbManager::class);
    $licenseCsvImport = new LicenseCsvImport($dbManager);
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
    $licenseCsvImport = new LicenseCsvImport($dbManager);

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
    $licenseCsvImport = new LicenseCsvImport($dbManager);

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
    $licenseCsvImport = new LicenseCsvImport($dbManager);

    Reflectory::invokeObjectsMethodnameWith($licenseCsvImport, 'handleCsv', array(array('shortname','foo','text','fullname','notes')));
    assertThat(Reflectory::getObjectsProperty($licenseCsvImport,'headrow'),is(notNullValue()));

    $dbManager->shouldReceive('getSingleRow')->with('SELECT rf_shortname,rf_source,rf_pk,rf_risk FROM license_ref WHERE rf_md5=md5($1)',anything())->andReturn(false);
    $dbManager->shouldReceive('prepare');
    $dbManager->shouldReceive('execute');
    $dbManager->shouldReceive('freeResult');
    $dbManager->shouldReceive('fetchArray')->andReturn(array('rf_pk'=>101,'rf_risk'=>1));
    Reflectory::setObjectsProperty($licenseCsvImport, 'nkMap', array('licA'=>false));
    Reflectory::invokeObjectsMethodnameWith($licenseCsvImport, 'handleCsv', array(array('licA','bar','txA','liceA','noteA')));
    assertThat(Reflectory::getObjectsProperty($licenseCsvImport, 'nkMap'),is(array('licA'=>101)));
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
    $licenseCsvImport = new LicenseCsvImport($dbManager);
    $msg = $licenseCsvImport->handleFile('/tmp/thisFileNameShouldNotExists');
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
    $licenseCsvImport = new LicenseCsvImport($dbManager);
    $msg = $licenseCsvImport->handleFile(__FILE__);
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
    $licenseCsvImport = new LicenseCsvImport($dbManager);
    $filename = tempnam("/tmp", "FOO");
    $handle = fopen($filename, 'w');
    fwrite($handle, "shortname,fullname,text");
    fclose($handle);
    $msg = $licenseCsvImport->handleFile($filename);
    assertThat($msg, startsWith( _('head okay')));
    unlink($filename);
  }
}
