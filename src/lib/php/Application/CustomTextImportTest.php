<?php
/*
 SPDX-FileCopyrightText: © 2025 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Data\License;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\Reflectory;
use Mockery as M;
use Monolog\Logger;

/**
 * @class CustomTextImportTest
 * @brief Unit tests for CustomTextImport — covering the C2 fix and
 *        import logic (alias mapping, duplicate detection, parseBoolean,
 *        normalizeBulkExportValues, associateLicenses exact-name lookup).
 */
class CustomTextImportTest extends \PHPUnit\Framework\TestCase
{
  /** @var DbManager|M\MockInterface */
  private $dbManager;
  /** @var UserDao|M\MockInterface */
  private $userDao;
  /** @var LicenseDao|M\MockInterface */
  private $licenseDao;
  /** @var CustomTextImport */
  private $importer;

  protected function setUp(): void
  {
    // Minimal Auth globals so Auth::getUserId/getGroupId don't explode
    $GLOBALS['SysConf']['auth']['UserId']  = 1;
    $GLOBALS['SysConf']['auth']['GroupId'] = 1;

    $this->dbManager = M::mock(DbManager::class);
    $this->userDao   = M::mock(UserDao::class);
    $this->licenseDao = M::mock(LicenseDao::class);

    $this->importer = new CustomTextImport($this->dbManager, $this->userDao, $this->licenseDao);
  }

  protected function tearDown(): void
  {
    M::close();
  }

  // ------------------------------------------------------------------ helpers

  /** Create a minimal License mock with getId() returning $id. */
  private function makeLicense(int $id): object
  {
    $lic = M::mock(License::class);
    $lic->shouldReceive('getId')->andReturn($id);
    return $lic;
  }

  // ================================================================ C2 fix: no backwards normalization

  /**
   * @test
   * Regression for C2: GPL-2.0-only must be looked up as-is (not mangled to GPL-2.0).
   * Before the fix, normalizeLicenseName would convert "GPL-2.0-only" to "GPL-2.0",
   * causing the DB lookup to fail and a junk license to be auto-created.
   */
  public function testAssociateLicensesLooksUpSpdxNameDirectly(): void
  {
    $spdxName = 'GPL-2.0-only';
    $cpPk = 42;

    // The lookup MUST use the exact SPDX shortname, not a mangled form.
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with($spdxName)
      ->once()
      ->andReturn($this->makeLicense(7));

    // No insertLicense call allowed — the license already exists.
    $this->licenseDao->shouldNotReceive('insertLicense');

    $this->dbManager->shouldReceive('getSingleRow')
      ->andReturn(null); // no existing association
    $this->dbManager->shouldReceive('insertTableRow')->once();

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'associateLicenses',
      [$cpPk, $spdxName, false]
    );

    $this->assertSame(1, $result['associated']);
    $this->assertEmpty($result['failed']);
    $this->assertEmpty($result['created']);
  }

  /**
   * @test
   * GPL-2.0-or-later must be looked up by its exact name, not mapped to GPL-2.0.
   */
  public function testAssociateLicensesLooksUpOrLaterNameDirectly(): void
  {
    $spdxName = 'GPL-2.0-or-later';
    $cpPk = 43;

    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with($spdxName)
      ->once()
      ->andReturn($this->makeLicense(8));

    $this->licenseDao->shouldNotReceive('insertLicense');

    $this->dbManager->shouldReceive('getSingleRow')->andReturn(null);
    $this->dbManager->shouldReceive('insertTableRow')->once();

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'associateLicenses',
      [$cpPk, $spdxName, false]
    );

    $this->assertSame(1, $result['associated']);
  }

  /**
   * @test
   * License name with surrounding whitespace is trimmed before lookup.
   */
  public function testAssociateLicensesTrimsWhitespace(): void
  {
    $cpPk = 44;

    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('MIT')           // trimmed
      ->once()
      ->andReturn($this->makeLicense(9));

    $this->dbManager->shouldReceive('getSingleRow')->andReturn(null);
    $this->dbManager->shouldReceive('insertTableRow')->once();

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'associateLicenses',
      [$cpPk, '  MIT  ', false]
    );

    $this->assertSame(1, $result['associated']);
  }

  /**
   * @test
   * Unknown license that passes isValidLicenseShortname → auto-created exactly once.
   */
  public function testAutoCreatesUnknownValidLicense(): void
  {
    $cpPk       = 45;
    $unknownLic = 'My-Custom-1.0';

    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with($unknownLic)->once()->andReturn(null);
    $this->licenseDao->shouldReceive('insertLicense')
      ->with($unknownLic, '', null)->once()->andReturn(99);
    $this->licenseDao->shouldReceive('getLicenseById')
      ->with(99)->once()->andReturn($this->makeLicense(99));

    $this->dbManager->shouldReceive('getSingleRow')->andReturn(null);
    $this->dbManager->shouldReceive('insertTableRow')->once();

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'associateLicenses',
      [$cpPk, $unknownLic, false, true]   // $removing=false, $allowCreate=true
    );

    $this->assertSame(1, $result['associated']);
    $this->assertContains($unknownLic, $result['created']);
  }

  /**
   * @test
   * Comma-separated license list is split and each name looked up independently.
   */
  public function testAssociateLicensesHandlesCommaSeparatedList(): void
  {
    $cpPk = 46;

    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('MIT')->once()->andReturn($this->makeLicense(1));
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('Apache-2.0')->once()->andReturn($this->makeLicense(2));

    $this->dbManager->shouldReceive('getSingleRow')->andReturn(null);
    $this->dbManager->shouldReceive('insertTableRow')->twice();

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'associateLicenses',
      [$cpPk, 'MIT, Apache-2.0', false]
    );

    $this->assertSame(2, $result['associated']);
  }

  // ================================================================ duplicate detection

  /**
   * @test
   * Importing a phrase whose text_md5 is already in the DB → reported as duplicate, not inserted.
   */
  public function testImportPhrasesSkipsDuplicateText(): void
  {
    $text = 'This software is provided as-is.';

    // Duplicate check returns a row → existing phrase
    $this->dbManager->shouldReceive('getSingleRow')
      ->andReturn(['cp_pk' => 1]);

    // insertPreparedAndReturn must NOT be called
    $this->dbManager->shouldNotReceive('insertPreparedAndReturn');

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'importSinglePhrase',
      [['text' => $text]]
    );

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Duplicate', $result['message']);
  }

  /**
   * @test
   * Empty text → rejected immediately with "Text is required".
   */
  public function testImportPhraseRejectsEmptyText(): void
  {
    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'importSinglePhrase',
      [['text' => '']]
    );

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('required', strtolower($result['message']));
  }

  // ================================================================ header alias mapping

  /**
   * @test
   * mapHeaders maps capitalised CSV header names to standard internal names.
   */
  public function testMapHeadersMapsCapitalisedNames(): void
  {
    $data = [
      'Text'               => 'some phrase',
      'Acknowledgement'    => 'ack text',
      'Comments'           => 'a comment',
      'Is Active'          => 'true',
      'Licenses To Add'    => 'MIT',
      'Licenses To Remove' => 'GPL-2.0-only',
    ];

    $mapped = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'mapHeaders',
      [$data]
    );

    $this->assertSame('some phrase', $mapped['text']);
    $this->assertSame('ack text', $mapped['acknowledgement']);
    $this->assertSame('a comment', $mapped['comments']);
    $this->assertSame('true', $mapped['is_active']);
    $this->assertSame('MIT', $mapped['licenses_to_add']);
    $this->assertSame('GPL-2.0-only', $mapped['licenses_to_remove']);
  }

  /**
   * @test
   * mapHeaders maps lower-case JSON export names.
   */
  public function testMapHeadersMapsLowerCaseNames(): void
  {
    $data = [
      'text'               => 'phrase',
      'acknowledgement'    => '',
      'licenses_to_add'    => 'MIT',
      'licenses_to_remove' => '',
    ];

    $mapped = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'mapHeaders',
      [$data]
    );

    $this->assertSame('phrase', $mapped['text']);
    $this->assertSame('MIT', $mapped['licenses_to_add']);
  }

  // ================================================================ parseBoolean

  /**
   * @test
   * parseBoolean accepts common truthy/falsy string representations.
   *
   * @dataProvider parseBooleanProvider
   */
  public function testParseBoolean($input, bool $expected): void
  {
    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'parseBoolean',
      [$input]
    );
    $this->assertSame($expected, $result);
  }

  public function parseBooleanProvider(): array
  {
    return [
      ['true',   true],
      ['TRUE',   true],
      ['1',      true],
      ['yes',    true],
      ['on',     true],
      ['active', true],
      ['false',  false],
      ['0',      false],
      ['no',     false],
      ['off',    false],
      ['',       false],
      [true,     true],
      [false,    false],
    ];
  }

  // ================================================================ normalizeBulkExportValues

  /**
   * @test
   * Pipe-separated acknowledgement from bulk CSV export is joined with "; ".
   */
  public function testNormalizeBulkExportValuesPipeAcknowledgement(): void
  {
    $mapped = ['acknowledgement' => 'First note | Second note | Third note'];

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'normalizeBulkExportValues',
      [$mapped]
    );

    $this->assertSame('First note; Second note; Third note', $result['acknowledgement']);
  }

  /**
   * @test
   * Array acknowledgement from JSON export is joined with "; ".
   */
  public function testNormalizeBulkExportValuesArrayAcknowledgement(): void
  {
    $mapped = ['acknowledgement' => ['Note A', 'Note B']];

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'normalizeBulkExportValues',
      [$mapped]
    );

    $this->assertSame('Note A; Note B', $result['acknowledgement']);
  }

  /**
   * @test
   * Literal \n escape sequences in text are restored to real newlines.
   */
  public function testNormalizeBulkExportValuesRestoresNewlines(): void
  {
    $mapped = ['text' => 'line one\\nline two\\nline three'];

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'normalizeBulkExportValues',
      [$mapped]
    );

    $this->assertSame("line one\nline two\nline three", $result['text']);
  }

  // ================================================================ importJsonData public API

  /**
   * @test
   * importJsonData returns a count message when given valid phrase data.
   */
  public function testImportJsonDataReturnsCountMessage(): void
  {
    // Duplicate check → no existing row
    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/custom_phrase/'), M::any(), M::any())
      ->andReturn(null);
    $this->dbManager->shouldReceive('insertPreparedAndReturn')
      ->andReturn(1);

    $msg = '';
    $result = $this->importer->importJsonData(
      [['text' => 'a unique phrase that is new']],
      $msg
    );

    $this->assertStringContainsString('1', $result);
  }

  /**
   * @test
   * importJsonData skips rows with no text and counts only successful ones.
   */
  public function testImportJsonDataSkipsRowsWithoutText(): void
  {
    $msg = '';
    $result = $this->importer->importJsonData(
      [['text' => ''], ['acknowledgement' => 'orphan']],
      $msg
    );

    // 0 phrases imported
    $this->assertStringContainsString('0', $result);
  }
}
