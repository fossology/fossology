<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\Db\DbManager;
use Mockery as M;

/**
 * @class BulkTextExportTest
 * @brief Test for class BulkTextExport
 */
class BulkTextExportTest extends \PHPUnit\Framework\TestCase
{
  /** @var int */
  private $assertCountBefore;

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
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    M::close();
  }

  /**
   * @test
    * -# Export CSV with a comma and newline in text.
    * -# Verify newlines are flattened to literal \n so each CSV record stays on one physical line.
   */
  public function testExportBulkTextCsvEscapesDelimiterAndNewlineInText()
  {
    $dbManager = M::mock(DbManager::class);
    $dbManager->shouldReceive('getRows')->once()->withAnyArgs()->andReturn(array(
      array(
        'rf_text' => "hello, world\nsecond line",
        'rf_shortname' => 'MIT',
        'removing' => 'f',
        'comment' => null,
        'acknowledgement' => null,
        'rf_active' => 't'
      )
    ));

    $bulkTextExport = new BulkTextExport($dbManager);
    $csv = $bulkTextExport->exportBulkText();

    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $csv);
    rewind($handle);

    $rows = array();
    while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
      $rows[] = $row;
    }
    fclose($handle);

    $this->assertCount(2, $rows);
    $this->assertSame("\xEF\xBB\xBFtext", $rows[0][0]);
    $this->assertSame('hello, world\\nsecond line', $rows[1][0]);
    $this->assertSame('MIT', $rows[1][1]);
    $this->assertSame(2, substr_count($csv, "\n"));
  }

  /**
   * @test
   */
  public function testSetDelimiterRejectsEmptyValue()
  {
    $dbManager = M::mock(DbManager::class);
    $bulkTextExport = new BulkTextExport($dbManager);

    $this->expectException(\InvalidArgumentException::class);
    $bulkTextExport->setDelimiter('');
  }

  /**
   * @test
   */
  public function testSetEnclosureRejectsSameValueAsDelimiter()
  {
    $dbManager = M::mock(DbManager::class);
    $bulkTextExport = new BulkTextExport($dbManager);

    $this->expectException(\InvalidArgumentException::class);
    $bulkTextExport->setEnclosure(',');
  }

  /**
   * @test
   * -# Export CSV with includeLicenseText enabled.
   * -# Verify license_text column appears with rf_text from license_ref for added licenses.
   */
  public function testExportBulkTextCsvIncludesLicenseText()
  {
    $dbManager = M::mock(DbManager::class);
    $dbManager->shouldReceive('getRows')->once()->withAnyArgs()->andReturn(array(
      array(
        'rf_text' => "some bulk text",
        'rf_shortname' => 'MIT',
        'removing' => 'f',
        'comment' => null,
        'acknowledgement' => null,
        'rf_active' => 't',
        'license_text' => 'Permission is hereby granted, free of charge...'
      ),
      array(
        'rf_text' => "some bulk text",
        'rf_shortname' => 'Apache-2.0',
        'removing' => 't',
        'comment' => null,
        'acknowledgement' => null,
        'rf_active' => 't',
        'license_text' => 'Apache License Version 2.0...'
      )
    ));

    $bulkTextExport = new BulkTextExport($dbManager);
    $csv = $bulkTextExport->exportBulkText(0, 0, false, true);

    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $csv);
    rewind($handle);

    $rows = array();
    while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
      $rows[] = $row;
    }
    fclose($handle);

    $this->assertCount(2, $rows);
    $this->assertSame('license_text', $rows[0][6]);
    $this->assertSame('MIT', $rows[1][1]);
    $this->assertSame('Apache-2.0', $rows[1][2]);
    $this->assertStringContainsString('Permission is hereby granted', $rows[1][6]);
    $this->assertStringNotContainsString('Apache License', $rows[1][6]);
  }

  /**
   * @test
   * -# Export CSV without includeLicenseText.
   * -# Verify license_text column does NOT appear.
   */
  public function testExportBulkTextCsvExcludesLicenseTextByDefault()
  {
    $dbManager = M::mock(DbManager::class);
    $dbManager->shouldReceive('getRows')->once()->withAnyArgs()->andReturn(array(
      array(
        'rf_text' => "some text",
        'rf_shortname' => 'MIT',
        'removing' => 'f',
        'comment' => null,
        'acknowledgement' => null,
        'rf_active' => 't'
      )
    ));

    $bulkTextExport = new BulkTextExport($dbManager);
    $csv = $bulkTextExport->exportBulkText();

    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $csv);
    rewind($handle);

    $rows = array();
    while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
      $rows[] = $row;
    }
    fclose($handle);

    $this->assertCount(2, $rows);
    $this->assertCount(6, $rows[0]);
    $this->assertSame('is_active', $rows[0][5]);
  }
}
