<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\BusinessRules\ObligationMap;
use Fossology\Lib\Db\DbManager;
use Mockery as M;

/**
 * @class ObligationCsvExportTest
 * @brief Test for ObligationCsvExport
 */
class ObligationCsvExportTest extends \PHPUnit\Framework\TestCase
{
  /** @var DbManager $dbManager Mock DbManager */
  private $dbManager;

  /** @var ObligationMap $obligationMap Mock ObligationMap */
  private $obligationMap;

  /** @var ObligationCsvExport $export Object under test */
  private $export;

  /** @var int $assertCountBefore */
  private $assertCountBefore;

  /**
   * @brief One time setup for test
   * @see PHPUnit::Framework::TestCase::setUp()
   */
  protected function setUp(): void
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $this->dbManager = M::mock(DbManager::class);
    $this->obligationMap = M::mock(ObligationMap::class);
    $this->export = new ObligationCsvExport($this->dbManager, $this->obligationMap);
  }

  /**
   * @brief Close mockery
   * @see PHPUnit::Framework::TestCase::tearDown()
   */
  protected function tearDown(): void
  {
    $this->addToAssertionCount(
      \Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    M::close();
  }

  /**
   * @brief Test for ObligationCsvExport::createCsv() bulk JSON export
   * @test
   * -# Call createCsv(0, true)
   * -# Verify that "Associated candidate Licenses" has no trailing slash
   */
  public function testCreateCsvBulkJson()
  {
    $rows = [
      [
        'ob_pk' => 1,
        'Type' => 'Obligation',
        'Obligation or Risk topic' => 'Source Code Offer',
        'Full Text' => 'Provide source code upon request',
        'Classification' => 'High',
        'Apply on modified source code' => 'Yes',
        'Comment' => 'Standard offer'
      ]
    ];

    $this->dbManager->shouldReceive('prepare')->once();
    $this->dbManager->shouldReceive('execute')->once()->andReturn('res');
    $this->dbManager->shouldReceive('fetchAll')->once()->andReturn($rows);
    $this->dbManager->shouldReceive('freeResult')->once();

    $this->obligationMap->shouldReceive('getLicenseList')->with(1)->once()->andReturn('GPL-2.0-only');
    $this->obligationMap->shouldReceive('getLicenseList')->with(1, true)->once()->andReturn('CandidateLic');

    $json = $this->export->createCsv(0, true);
    $decoded = json_decode($json, true);

    $this->assertIsArray($decoded);
    $this->assertCount(1, $decoded);
    $this->assertArrayHasKey('Associated candidate Licenses', $decoded[0]);
    $this->assertArrayNotHasKey('Associated candidate Licenses/', $decoded[0]);
    $this->assertEquals('CandidateLic', $decoded[0]['Associated candidate Licenses']);
    $this->assertEquals('GPL-2.0-only', $decoded[0]['Associated Licenses']);
  }

  /**
   * @brief Test for ObligationCsvExport::createCsv() single JSON export
   * @test
   * -# Call createCsv(5, true)
   * -# Verify that single obligation is exported with valid keys
   */
  public function testCreateCsvSingleJson()
  {
    $row = [
      'ob_pk' => 5,
      'Type' => 'Obligation',
      'Obligation or Risk topic' => 'Notice',
      'Full Text' => 'Retain copyright notice',
      'Classification' => 'Medium',
      'Apply on modified source code' => 'No',
      'Comment' => 'Retain notice'
    ];

    $this->dbManager->shouldReceive('getSingleRow')->once()->andReturn($row);
    $this->obligationMap->shouldReceive('getLicenseList')->with(5)->once()->andReturn('MIT;BSD-3-Clause');
    $this->obligationMap->shouldReceive('getLicenseList')->with(5, true)->once()->andReturn('');

    $json = $this->export->createCsv(5, true);
    $decoded = json_decode($json, true);

    $this->assertIsArray($decoded);
    $this->assertCount(1, $decoded);
    $this->assertArrayHasKey('Associated candidate Licenses', $decoded[0]);
    $this->assertEquals('MIT;BSD-3-Clause', $decoded[0]['Associated Licenses']);
  }

  /**
   * @brief Test for ObligationCsvExport::createCsv() with non-existent obligation ID
   * @test
   * -# Call createCsv(999, true) where ID does not exist in DB
   * -# Verify it does not throw TypeError on array_shift
   */
  public function testCreateCsvNonExistentObligationReturnsEmpty()
  {
    $this->dbManager->shouldReceive('getSingleRow')->once()->andReturn(false);

    $json = $this->export->createCsv(999, true);
    $decoded = json_decode($json, true);

    $this->assertIsArray($decoded);
    $this->assertEmpty($decoded);
  }

  /**
   * @brief Test for ObligationCsvExport::createCsv() CSV output
   * @test
   * -# Call createCsv(0, false)
   * -# Verify CSV headers and content
   */
  public function testCreateCsvBulkCsvFormat()
  {
    $rows = [
      [
        'ob_pk' => 10,
        'Type' => 'Obligation',
        'Obligation or Risk topic' => 'Topic 1',
        'Full Text' => 'Text 1',
        'Classification' => 'Low',
        'Apply on modified source code' => 'Yes',
        'Comment' => 'Comment 1'
      ]
    ];

    $this->dbManager->shouldReceive('prepare')->once();
    $this->dbManager->shouldReceive('execute')->once()->andReturn('res');
    $this->dbManager->shouldReceive('fetchAll')->once()->andReturn($rows);
    $this->dbManager->shouldReceive('freeResult')->once();

    $this->obligationMap->shouldReceive('getLicenseList')->with(10)->once()->andReturn('Apache-2.0');
    $this->obligationMap->shouldReceive('getLicenseList')->with(10, true)->once()->andReturn('');

    $csv = $this->export->createCsv(0, false);
    $this->assertStringContainsString('Associated candidate Licenses', $csv);
    $this->assertStringContainsString('Topic 1', $csv);
  }

  /**
   * @brief Test for ObligationCsvExport::createCsv() with non-existent obligation ID for CSV
   * @test
   * -# Call createCsv(999, false) where ID does not exist in DB
   * -# Verify it returns CSV headers only without throwing TypeError
   */
  public function testCreateCsvNonExistentObligationCsvReturnsHeadersOnly()
  {
    $this->dbManager->shouldReceive('getSingleRow')->once()->andReturn(false);

    $csv = $this->export->createCsv(999, false);
    $this->assertStringContainsString('Associated candidate Licenses', $csv);
    $lines = explode("\n", trim($csv));
    $this->assertCount(1, $lines);
  }

  /**
   * @brief Test for ObligationCsvExport::setDelimiter()
   * @test
   * -# Set delimiter
   * -# Check that delimiter property is updated
   */
  public function testSetDelimiter()
  {
    $this->export->setDelimiter(';');
    $reflection = new \ReflectionClass($this->export);
    $delimiter = $reflection->getProperty('delimiter');
    $delimiter->setAccessible(true);
    $this->assertEquals(';', $delimiter->getValue($this->export));
  }

  /**
   * @brief Test for ObligationCsvExport::setEnclosure()
   * @test
   * -# Set enclosure
   * -# Check that enclosure property is updated
   */
  public function testSetEnclosure()
  {
    $this->export->setEnclosure("'");
    $reflection = new \ReflectionClass($this->export);
    $enclosure = $reflection->getProperty('enclosure');
    $enclosure->setAccessible(true);
    $this->assertEquals("'", $enclosure->getValue($this->export));
  }
}

