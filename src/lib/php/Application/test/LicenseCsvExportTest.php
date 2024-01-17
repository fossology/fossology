<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;

/**
 * @class LicenseCsvExportTest
 * @brief Test for class LicenseCsvExport
 */
class LicenseCsvExportTest extends \PHPUnit\Framework\TestCase
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
   * @brief Test for LicenseCsvExport::createCsv()
   * @test
   * -# Setup test DB and insert some licenses.
   * -# Call LicenseCsvExport::createCsv().
   * -# Check if the file returned is correct.
   * -# Test with different delimiters.
   */
  public function testCreateCsv()
  {
    $testDb = new TestPgDb("licenseCsvExport");
    $testDb->createPlainTables(array('license_ref','license_map','groups','obligation_ref','obligation_map'));
    $testDb->createInheritedTables(array('license_candidate'));
    $dbManager = $testDb->getDbManager();
    $licenses = array();
    $candLicenses = array();
    $dbManager->insertTableRow('groups', array(
      'group_pk' => 2, 'group_name' => 'test'
    ));
    for ($i = 1; $i < 4; $i ++) {
      $licenses[$i] = array(
        'rf_pk' => $i,
        'rf_shortname' => 'lic' . $i,
        'rf_spdx_id' => 'lrf-lic' . $i,
        'rf_fullname' => 'lice' . $i,
        'rf_text' => 'text' . $i,
        'rf_url' => $i . $i,
        'rf_notes' => 'note' . $i,
        'rf_source' => 's' . $i,
        'rf_detector_type' => 1,
        'rf_risk' => ($i - 1)
      );
      $dbManager->insertTableRow('license_ref', $licenses[$i]);
    }
    for ($i = 1; $i <= 4; $i ++) {
      $candLicenses[$i] = array(
        'rf_pk' => $i + 4,
        'rf_shortname' => 'candlic' . $i,
        'rf_fullname' => 'candlice' . $i,
        'rf_spdx_id' => null,
        'rf_text' => 'text' . $i,
        'rf_url' => $i . $i,
        'rf_notes' => 'note' . $i,
        'rf_source' => 's' . $i,
        'rf_detector_type' => 1,
        'rf_risk' => ($i - 1),
        'marydone' => false,
        'group_fk' => 2
      );
      if ($i % 2 == 0) {
        $candLicenses[$i]['marydone'] = true;
      }
      $dbManager->insertTableRow('license_candidate', $candLicenses[$i]);
    }

    $dbManager->insertTableRow('license_map', array('rf_fk'=>3,'rf_parent'=>1,'usage'=>LicenseMap::CONCLUSION));
    $dbManager->insertTableRow('license_map', array('rf_fk'=>3,'rf_parent'=>2,'usage'=>LicenseMap::REPORT));

    $licenseCsvExport = new LicenseCsvExport($dbManager);
    $head = array('shortname','fullname', 'spdx_id','text','parent_shortname','report_shortname','url','notes','source','risk','group', 'obligations');
    $out = fopen('php://output', 'w');

    $csv = $licenseCsvExport->createCsv();
    ob_start();
    fputs($out, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
    fputcsv($out, $head);
    fputcsv($out, array($licenses[1]['rf_shortname'],
        $licenses[1]['rf_fullname'],
        $licenses[1]['rf_spdx_id'],
        $licenses[1]['rf_text'],
        null,
        null,
        $licenses[1]['rf_url'],
        $licenses[1]['rf_notes'],
        $licenses[1]['rf_source'],
        $licenses[1]['rf_risk'],
        null, null));

    fputcsv($out, array($licenses[2]['rf_shortname'],
        $licenses[2]['rf_fullname'],
        $licenses[2]['rf_spdx_id'],
        $licenses[2]['rf_text'],
        null,
        null,
        $licenses[2]['rf_url'],
        $licenses[2]['rf_notes'],
        $licenses[2]['rf_source'],
        $licenses[2]['rf_risk'],
        null, null));

    fputcsv($out, array($licenses[3]['rf_shortname'],
        $licenses[3]['rf_fullname'],
        $licenses[3]['rf_spdx_id'],
        $licenses[3]['rf_text'],
        $licenses[1]['rf_shortname'],
        $licenses[2]['rf_shortname'],
        $licenses[3]['rf_url'],
        $licenses[3]['rf_notes'],
        $licenses[3]['rf_source'],
        $licenses[3]['rf_risk'],
        null, null));

    fputcsv($out, array($candLicenses[2]['rf_shortname'],
      $candLicenses[2]['rf_fullname'],
      LicenseRef::convertToSpdxId($candLicenses[2]['rf_shortname'], $candLicenses[2]['rf_spdx_id']),
      $candLicenses[2]['rf_text'],
      null,
      null,
      $candLicenses[2]['rf_url'],
      $candLicenses[2]['rf_notes'],
      $candLicenses[2]['rf_source'],
      $candLicenses[2]['rf_risk'],
      "test", null));

    fputcsv($out, array($candLicenses[4]['rf_shortname'],
      $candLicenses[4]['rf_fullname'],
      LicenseRef::convertToSpdxId($candLicenses[4]['rf_shortname'], $candLicenses[4]['rf_spdx_id']),
      $candLicenses[4]['rf_text'],
      null,
      null,
      $candLicenses[4]['rf_url'],
      $candLicenses[4]['rf_notes'],
      $candLicenses[4]['rf_source'],
      $candLicenses[4]['rf_risk'],
      "test", null));
    $expected = ob_get_contents();
    ob_end_clean();

    assertThat($csv, is(equalTo($expected)));

    $delimiter = '|';
    $licenseCsvExport->setDelimiter($delimiter);
    $csv3 = $licenseCsvExport->createCsv(3);
    ob_start();
    fputs($out, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
    fputcsv($out, $head, $delimiter);
    fputcsv($out, array($licenses[3]['rf_shortname'],
          $licenses[3]['rf_fullname'],
          $licenses[3]['rf_spdx_id'],
          $licenses[3]['rf_text'],
          $licenses[1]['rf_shortname'],
          $licenses[2]['rf_shortname'],
          $licenses[3]['rf_url'],
          $licenses[3]['rf_notes'],
          $licenses[3]['rf_source'],
          $licenses[3]['rf_risk'],
          null, null
        ),
        $delimiter);
    $expected3 = ob_get_contents();
    ob_end_clean();
    assertThat($csv3, is(equalTo($expected3)));
  }

  /**
   * @brief Test for LicenseCsvExport::setDelimiter()
   * @test
   * -# Initialize LicenseCsvExport.
   * -# Set a new delimiter using LicenseCsvExport::setDelimiter().
   * -# Check if the delimiter is changed.
   * -# Set a new delimiter using LicenseCsvExport::setDelimiter().
   * -# Check if the delimiter is changed with only the first character passed.
   */
  public function testSetDelimiter()
  {
    $dbManager = M::mock(DbManager::class);
    $licenseCsvExport = new LicenseCsvExport($dbManager);
    $reflection = new \ReflectionClass($licenseCsvExport);
    $delimiter = $reflection->getProperty('delimiter');
    $delimiter->setAccessible(true);

    $licenseCsvExport->setDelimiter('|');
    assertThat($delimiter->getValue($licenseCsvExport),is('|'));

    $licenseCsvExport->setDelimiter('<>');
    assertThat($delimiter->getValue($licenseCsvExport),is('<'));
  }

  /**
   * @brief Test for LicenseCsvExport::setEnclosure()
   * @test
   * -# Initialize LicenseCsvExport.
   * -# Set a new enclosure using LicenseCsvExport::setEnclosure().
   * -# Check if the enclosure is changed.
   * -# Set a new enclosure using LicenseCsvExport::setEnclosure().
   * -# Check if the enclosure is changed with only the first character passed.
   */
  public function testSetEnclosure()
  {
    $dbManager = M::mock(DbManager::class);
    $licenseCsvExport = new LicenseCsvExport($dbManager);
    $reflection = new \ReflectionClass($licenseCsvExport);
    $enclosure = $reflection->getProperty('enclosure');
    $enclosure->setAccessible(true);

    $licenseCsvExport->setEnclosure('|');
    assertThat($enclosure->getValue($licenseCsvExport),is('|'));

    $licenseCsvExport->setEnclosure('<>');
    assertThat($enclosure->getValue($licenseCsvExport),is('<'));
  }
}
