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
 * @class ObligationCsvImportTest
 * @brief Test for ObligationCsvImport
 */
class ObligationCsvImportTest extends \PHPUnit\Framework\TestCase
{
  /** @var DbManager $dbManager Mock DbManager */
  private $dbManager;

  /** @var ObligationMap $obligationMap Mock ObligationMap */
  private $obligationMap;

  /** @var ObligationCsvImport $import Object under test */
  private $import;

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
    $this->import = new ObligationCsvImport($this->dbManager, $this->obligationMap);
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
   * @brief Test for ObligationCsvImport::handleRowJson() key aliasing
   * @test
   * -# Pass exported obligation keys into handleRowJson()
   * -# Verify aliased keys are properly mapped to canonical internal keys
   */
  public function testHandleRowJsonAliases()
  {
    $row = [
      'Type' => 'Obligation',
      'Obligation or Risk topic' => 'Source Code',
      'Full Text' => 'Provide source code',
      'Classification' => 'High',
      'Apply on modified source code' => 'Yes',
      'Comment' => 'Sample comment',
      'Associated Licenses' => 'GPL-2.0-only',
      'Associated candidate Licenses' => 'Candidate-1'
    ];

    $mapped = $this->import->handleRowJson($row);

    $this->assertArrayHasKey('type', $mapped);
    $this->assertEquals('Obligation', $mapped['type']);
    $this->assertArrayHasKey('topic', $mapped);
    $this->assertEquals('Source Code', $mapped['topic']);
    $this->assertArrayHasKey('text', $mapped);
    $this->assertEquals('Provide source code', $mapped['text']);
    $this->assertArrayHasKey('classification', $mapped);
    $this->assertEquals('High', $mapped['classification']);
    $this->assertArrayHasKey('modifications', $mapped);
    $this->assertEquals('Yes', $mapped['modifications']);
    $this->assertArrayHasKey('comment', $mapped);
    $this->assertEquals('Sample comment', $mapped['comment']);
    $this->assertArrayHasKey('licnames', $mapped);
    $this->assertEquals('GPL-2.0-only', $mapped['licnames']);
    $this->assertArrayHasKey('candidatenames', $mapped);
    $this->assertEquals('Candidate-1', $mapped['candidatenames']);
  }

  /**
   * @brief Test importing standard FOSSology JSON obligations (without external_id)
   * @test
   * -# Pass standard exported JSON obligations to importJsonData()
   * -# Verify it delegates to handleCsvObligation without requiring external_id
   */
  public function testImportJsonDataStandardObligation()
  {
    $data = [
      [
        'Type' => 'Obligation',
        'Obligation or Risk topic' => 'Notice Retention',
        'Full Text' => 'Retain all notices',
        'Classification' => 'Medium',
        'Apply on modified source code' => 'No',
        'Comment' => 'Retain all copyright notices',
        'Associated Licenses' => 'MIT',
        'Associated candidate Licenses' => ''
      ]
    ];

    // getKeyFromTopicAndText expectation: check if obligation already exists
    $this->dbManager->shouldReceive('getSingleRow')
      ->with('SELECT ob_pk FROM obligation_ref WHERE ob_topic=$1 AND ob_md5=md5($2)', ['Notice Retention', 'Retain all notices'])
      ->once()
      ->andReturn(['ob_pk' => 12]);

    // updateOtherFields expectation
    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/SELECT ob_topic, ob_text/'), [12], M::any())
      ->once()
      ->andReturn([
        'ob_topic' => 'Notice Retention',
        'ob_text' => 'Retain all notices',
        'ob_classification' => 'Medium',
        'ob_modifications' => 'No',
        'ob_comment' => 'Retain all copyright notices',
        'ob_type' => 'Obligation'
      ]);

    // compareLicList expectation for licnames
    $this->obligationMap->shouldReceive('getLicenseList')
      ->with(12, false)
      ->once()
      ->andReturn('MIT');

    // compareLicList expectation for candidatenames
    $this->obligationMap->shouldReceive('getLicenseList')
      ->with(12, true)
      ->once()
      ->andReturn('');

    $result = $this->import->importJsonData($data, '');
    $this->assertStringNotContainsString('Error: external_id cannot be empty', $result);
    $this->assertStringContainsString('No Changes in AssociateLicense', $result);
    $this->assertStringContainsString('No Changes in CandidateLicense', $result);
  }

  /**
   * @brief Test importing LicenseDB JSON obligations (with external_id)
   * @test
   * -# Pass JSON with external_id to importJsonData()
   * -# Verify it delegates to handleLicenseDBObligationImport()
   */
  public function testImportJsonDataLicenseDBObligation()
  {
    $data = [
      [
        'external_id' => 'EXT-100',
        'topic' => 'Topic DB',
        'text' => 'Text DB',
        'type' => 'Obligation',
        'classification' => 'Low',
        'comment' => 'Comment DB',
        'license_ids' => []
      ]
    ];

    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/SELECT ob_pk FROM obligation_ref WHERE ob_external_id=\$1/'), ['EXT-100'], M::any())
      ->once()
      ->andReturn(['ob_pk' => 25]);

    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/SELECT ob_topic, ob_text/'), [25], M::any())
      ->once()
      ->andReturn([
        'ob_topic' => 'Topic DB',
        'ob_text' => 'Text DB',
        'ob_classification' => 'Low',
        'ob_modifications' => 'False',
        'ob_comment' => 'Comment DB',
        'ob_type' => 'Obligation'
      ]);

    $this->dbManager->shouldReceive('begin')->once();
    $this->dbManager->shouldReceive('getRows')
      ->with(M::pattern('/SELECT rf_fk FROM obligation_map WHERE ob_fk = \$1/'), [25], M::any())
      ->once()
      ->andReturn([]);

    $this->obligationMap->shouldReceive('unassociateLicenseFromLicenseList')->with(25, [])->once();
    $this->obligationMap->shouldReceive('associateLicenseFromLicenseList')->with(25, [])->once();
    $this->dbManager->shouldReceive('commit')->once();

    $result = $this->import->importJsonData($data, '');
    $this->assertStringNotContainsString('Error: external_id cannot be empty', $result);
  }

  /**
   * @brief Test handleFile with malformed JSON
   * @test
   * -# Feed invalid JSON syntax to handleFile()
   * -# Verify clean error handling without unhandled exceptions
   */
  public function testHandleFileInvalidJson()
  {
    $tmp = tempnam(sys_get_temp_dir(), 'ob_inv_') . '.json';
    file_put_contents($tmp, '{not-valid-json}');

    $result = $this->import->handleFile($tmp, 'json');
    $this->assertStringContainsString('Error decoding JSON', $result);

    @unlink($tmp);
  }

  /**
   * @brief Test for ObligationCsvImport::handleRowJson() with legacy trailing slash
   * @test
   * -# Pass legacy exported key "Associated candidate Licenses/" into handleRowJson()
   * -# Verify it maps to "candidatenames" for backward compatibility
   */
  public function testHandleRowJsonLegacyTrailingSlash()
  {
    $row = [
      'Associated candidate Licenses/' => 'Candidate-Legacy'
    ];

    $mapped = $this->import->handleRowJson($row);
    $this->assertArrayHasKey('candidatenames', $mapped);
    $this->assertEquals('Candidate-Legacy', $mapped['candidatenames']);
  }

  /**
   * @brief Test importing new standard obligation that does not yet exist in DB
   * @test
   * -# Pass standard JSON obligation for new entry
   * -# Verify INSERT and license associations are triggered
   */
  public function testImportJsonDataNewObligation()
  {
    $data = [
      [
        'Type' => 'Obligation',
        'Obligation or Risk topic' => 'Brand New Topic',
        'Full Text' => 'Brand New Full Text',
        'Classification' => 'High',
        'Apply on modified source code' => 'Yes',
        'Comment' => 'Brand new comment',
        'Associated Licenses' => 'MIT',
        'Associated candidate Licenses' => 'Candidate-1'
      ]
    ];

    // getKeyFromTopicAndText -> not found
    $this->dbManager->shouldReceive('getSingleRow')
      ->with('SELECT ob_pk FROM obligation_ref WHERE ob_topic=$1 AND ob_md5=md5($2)', ['Brand New Topic', 'Brand New Full Text'])
      ->once()
      ->andReturn(false);

    // INSERT into obligation_ref
    $this->dbManager->shouldReceive('prepare')->once();
    $this->dbManager->shouldReceive('execute')->once()->andReturn('insert_res');
    $this->dbManager->shouldReceive('fetchArray')->with('insert_res')->once()->andReturn(['ob_pk' => 99]);
    $this->dbManager->shouldReceive('freeResult')->with('insert_res')->once();

    // AssociateWithLicenses for licnames
    $this->obligationMap->shouldReceive('getIdFromShortname')->with('MIT', false)->once()->andReturn([101]);
    $this->obligationMap->shouldReceive('associateLicenseFromLicenseList')->with(99, [101], false)->once()->andReturn(true);

    // AssociateWithLicenses for candidatenames
    $this->obligationMap->shouldReceive('getIdFromShortname')->with('Candidate-1', true)->once()->andReturn([202]);
    $this->obligationMap->shouldReceive('associateLicenseFromLicenseList')->with(99, [202], true)->once()->andReturn(true);

    $result = $this->import->importJsonData($data, '');
    $this->assertStringContainsString('Obligation with id=99 was added successfully', $result);
    $this->assertStringContainsString('MIT were associated', $result);
    $this->assertStringContainsString('Candidate-1 were associated', $result);
  }

  /**
   * @brief Test importJsonData with non-array or empty data
   * @test
   * -# Pass null or empty array
   * -# Verify it returns unchanged message without errors
   */
  public function testImportJsonDataInvalidOrEmpty()
  {
    $this->assertEquals('initial', $this->import->importJsonData(null, 'initial'));
    $this->assertEquals('initial', $this->import->importJsonData([], 'initial'));
    $this->assertEquals('initial', $this->import->importJsonData(['not-an-array'], 'initial'));
  }

  /**
   * @brief Test round-trip export from ObligationCsvExport and re-import into ObligationCsvImport
   * @test
   * -# Generate JSON export with ObligationCsvExport
   * -# Pass generated JSON to ObligationCsvImport::importJsonData()
   * -# Verify successful import without missing key or external_id error
   */
  public function testRoundTripExportAndImport()
  {
    $exportRows = [
      [
        'ob_pk' => 1,
        'Type' => 'Obligation',
        'Obligation or Risk topic' => 'Exported Topic',
        'Full Text' => 'Exported Text',
        'Classification' => 'High',
        'Apply on modified source code' => 'Yes',
        'Comment' => 'Exported Comment'
      ]
    ];

    $this->dbManager->shouldReceive('prepare')->once();
    $this->dbManager->shouldReceive('execute')->once()->andReturn('res');
    $this->dbManager->shouldReceive('fetchAll')->once()->andReturn($exportRows);
    $this->dbManager->shouldReceive('freeResult')->once();

    $this->obligationMap->shouldReceive('getLicenseList')->with(1)->once()->andReturn('GPL-2.0-only');
    $this->obligationMap->shouldReceive('getLicenseList')->with(1, true)->once()->andReturn('Candidate-1');

    $exporter = new ObligationCsvExport($this->dbManager, $this->obligationMap);
    $json = $exporter->createCsv(0, true);
    $exportedData = json_decode($json, true);

    $this->assertArrayHasKey('Associated candidate Licenses', $exportedData[0]);
    $this->assertArrayNotHasKey('Associated candidate Licenses/', $exportedData[0]);

    $this->dbManager->shouldReceive('getSingleRow')
      ->with('SELECT ob_pk FROM obligation_ref WHERE ob_topic=$1 AND ob_md5=md5($2)', ['Exported Topic', 'Exported Text'])
      ->once()
      ->andReturn(false);

    $this->dbManager->shouldReceive('prepare')->once();
    $this->dbManager->shouldReceive('execute')->once()->andReturn('insert_res');
    $this->dbManager->shouldReceive('fetchArray')->with('insert_res')->once()->andReturn(['ob_pk' => 10]);
    $this->dbManager->shouldReceive('freeResult')->with('insert_res')->once();

    $this->obligationMap->shouldReceive('getIdFromShortname')->with('GPL-2.0-only', false)->once()->andReturn([101]);
    $this->obligationMap->shouldReceive('associateLicenseFromLicenseList')->with(10, [101], false)->once()->andReturn(true);

    $this->obligationMap->shouldReceive('getIdFromShortname')->with('Candidate-1', true)->once()->andReturn([202]);
    $this->obligationMap->shouldReceive('associateLicenseFromLicenseList')->with(10, [202], true)->once()->andReturn(true);

    $result = $this->import->importJsonData($exportedData, '');
    $this->assertStringNotContainsString('Error: external_id cannot be empty', $result);
    $this->assertStringContainsString('Obligation with id=10 was added successfully', $result);
    $this->assertStringContainsString('GPL-2.0-only were associated', $result);
    $this->assertStringContainsString('Candidate-1 were associated', $result);
  }
}

