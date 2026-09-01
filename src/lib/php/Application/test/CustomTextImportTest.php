<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Data\License;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\Reflectory;
use Mockery as M;

/**
 * @class CustomTextImportTest
 * @brief Test for class CustomTextImport
 */
class CustomTextImportTest extends \PHPUnit\Framework\TestCase
{
  /** @var int */
  private $assertCountBefore;

  /** @var DbManager|M\MockInterface */
  private $dbManager;
  /** @var UserDao|M\MockInterface */
  private $userDao;
  /** @var LicenseDao|M\MockInterface */
  private $licenseDao;
  /** @var CustomTextImport */
  private $importer;
  /** @var string[] */
  private $tmpFiles = [];

  protected function setUp(): void
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();

    // Auth::getUserId() / getGroupId() read these globals.
    $GLOBALS['SysConf']['auth']['UserId'] = 1;
    $GLOBALS['SysConf']['auth']['GroupId'] = 1;

    $this->dbManager = M::mock(DbManager::class);
    $this->dbManager->shouldReceive('begin')->andReturnNull();
    $this->dbManager->shouldReceive('commit')->andReturnNull();
    $this->dbManager->shouldReceive('rollback')->andReturnNull();
    $this->userDao = M::mock(UserDao::class);
    $this->licenseDao = M::mock(LicenseDao::class);

    $this->importer = new CustomTextImport(
      $this->dbManager,
      $this->userDao,
      $this->licenseDao
    );
  }

  protected function tearDown(): void
  {
    $this->addToAssertionCount(
      \Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore
    );
    foreach ($this->tmpFiles as $f) {
      if (file_exists($f)) {
        unlink($f);
      }
    }
    M::close();
  }

  private function makeLicense(int $id): object
  {
    $lic = M::mock(License::class);
    $lic->shouldReceive('getId')->andReturn($id);
    return $lic;
  }

  private function writeTempFile(string $content, string $ext): string
  {
    $path = tempnam(sys_get_temp_dir(), 'cti_test_') . '.' . $ext;
    file_put_contents($path, $content);
    $this->tmpFiles[] = $path;
    return $path;
  }

  /** Force mapHeaders()/importSinglePhrase() into "CSV source" mode without going through a real file. */
  private function markAsCsvSource(): void
  {
    $prop = new \ReflectionProperty(CustomTextImport::class, 'unescapeNewlines');
    $prop->setAccessible(true);
    $prop->setValue($this->importer, true);
  }

  /**
   * @test
   * -# Build a flat CSV row using the headers produced by BulkTextExport.
   * -# Call mapHeaders().
   * -# Verify the result carries a licenses[] array built from the flat columns.
   */
  public function testMapHeadersBuildsLicensesArrayFromFlatCsvRow(): void
  {
    $row = [
      'Text' => 'some scanning text',
      'Is Active' => 'true',
      'License Shortname' => 'MIT',
      'Removing' => 'false',
      'Comment' => 'a comment',
      'License Text' => 'license text here',
      'Acknowledgement' => 'ack text',
    ];

    $mapped = Reflectory::invokeObjectsMethodnameWith(
      $this->importer, 'mapHeaders', [$row]
    );

    $this->assertSame('some scanning text', $mapped['text']);
    $this->assertIsArray($mapped['licenses']);
    $this->assertCount(1, $mapped['licenses']);
    $this->assertSame('MIT', $mapped['licenses'][0]['shortname']);
    $this->assertFalse($mapped['licenses'][0]['removing']);
    $this->assertSame('license text here', $mapped['licenses'][0]['reportinfo']);
    $this->assertSame('ack text', $mapped['licenses'][0]['acknowledgement']);
  }

  /**
   * @test
   * -# Build a row with 'Removing' set to a non-literal-"true" truthy value ('1').
   * -# Verify it is still parsed as a remove mapping.
   *    Regression: the old code only recognised the exact string "true";
   *    "1"/"yes"/"t" silently became an add mapping instead of a remove.
   */
  public function testMapHeadersParsesRemovingLeniently(): void
  {
    $row = [
      'Text' => 'phrase', 'License Shortname' => 'MIT', 'Removing' => '1',
    ];

    $mapped = Reflectory::invokeObjectsMethodnameWith(
      $this->importer, 'mapHeaders', [$row]
    );

    $this->assertTrue($mapped['licenses'][0]['removing']);
  }

  /**
   * @test
   * -# Build a row that already contains a nested licenses[] array (JSON format).
   * -# Verify mapHeaders passes it through unchanged.
   */
  public function testMapHeadersPassesThroughNestedLicensesArray(): void
  {
    $licenses = [
      ['shortname' => 'Apache-2.0', 'removing' => false,
       'comment' => '', 'reportinfo' => '', 'acknowledgement' => ''],
      ['shortname' => 'MIT', 'removing' => true,
       'comment' => '', 'reportinfo' => '', 'acknowledgement' => ''],
    ];
    $row = ['text' => 'json phrase', 'licenses' => $licenses];

    $mapped = Reflectory::invokeObjectsMethodnameWith(
      $this->importer, 'mapHeaders', [$row]
    );

    $this->assertCount(2, $mapped['licenses']);
    $this->assertSame('Apache-2.0', $mapped['licenses'][0]['shortname']);
    $this->assertSame('MIT', $mapped['licenses'][1]['shortname']);
  }

  /**
   * @test
   * -# Mark the importer as reading a CSV source (unescapeNewlines = true).
   * -# Pass a text value containing literal \n escape sequences (as written by BulkTextExport).
   * -# Verify mapHeaders restores them to real newlines.
   */
  public function testMapHeadersRestoresEscapedNewlinesForCsvSource(): void
  {
    $this->markAsCsvSource();
    $row = ['text' => "line one\\nline two\\nline three"];

    $mapped = Reflectory::invokeObjectsMethodnameWith(
      $this->importer, 'mapHeaders', [$row]
    );

    $this->assertSame("line one\nline two\nline three", $mapped['text']);
  }

  /**
   * @test
   * -# Leave the importer in JSON mode (unescapeNewlines = false, the default).
   * -# Pass text containing a literal backslash-n two-character sequence
   *    (e.g. from source code, not an export-time escape).
   * -# Verify mapHeaders leaves it untouched.
   *    Regression: unescaping unconditionally would corrupt any JSON-sourced
   *    phrase whose real content contains the two characters '\' 'n'.
   */
  public function testMapHeadersDoesNotUnescapeForJsonSource(): void
  {
    $row = ['text' => "printf(\"hi\\\\n\");"]; // literal: printf("hi\n");

    $mapped = Reflectory::invokeObjectsMethodnameWith(
      $this->importer, 'mapHeaders', [$row]
    );

    $this->assertSame("printf(\"hi\\\\n\");", $mapped['text']);
  }

  /**
   * @test
   * -# Mark the importer as CSV source.
   * -# Pass per-license comment/reportinfo/acknowledgement containing escaped newlines.
   * -# Verify all three are unescaped, not just the phrase text.
   */
  public function testMapHeadersUnescapesLicenseMetadataForCsvSource(): void
  {
    $this->markAsCsvSource();
    $row = [
      'text' => 'phrase',
      'licenses' => [[
        'shortname' => 'MIT',
        'removing' => false,
        'comment' => "line1\\nline2",
        'reportinfo' => "a\\nb",
        'acknowledgement' => "x\\ny",
      ]],
    ];

    $mapped = Reflectory::invokeObjectsMethodnameWith(
      $this->importer, 'mapHeaders', [$row]
    );

    $this->assertSame("line1\nline2", $mapped['licenses'][0]['comment']);
    $this->assertSame("a\nb", $mapped['licenses'][0]['reportinfo']);
    $this->assertSame("x\ny", $mapped['licenses'][0]['acknowledgement']);
  }

  /**
   * @test
   * -# Call parseBoolean with various truthy and falsy string/bool values.
   * -# Verify each maps to the expected bool.
   *
   * @dataProvider parseBooleanProvider
   */
  public function testParseBoolean($input, bool $expected): void
  {
    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer, 'parseBoolean', [$input]
    );
    $this->assertSame($expected, $result);
  }

  public static function parseBooleanProvider(): array
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

  /**
   * @test
   * -# Look up a license by shortname while a groupId is supplied.
   * -# Verify getLicenseByShortName() is called WITH that groupId.
   *    Regression: candidate licenses only resolve when
   *    LicenseDao::getLicenseByCondition() gets a non-null groupId.
   */
  public function testAssociateLicensesPassesGroupIdForCandidateResolution(): void
  {
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('CAND-1.0', 7)
      ->once()
      ->andReturn($this->makeLicense(50));

    $this->dbManager->shouldReceive('getSingleRow')->andReturn(null);
    $this->dbManager->shouldReceive('insertTableRow')->once();

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'associateLicensesWithMetadata',
      [1, [['shortname' => 'CAND-1.0', 'removing' => false,
            'comment' => '', 'reportinfo' => '', 'acknowledgement' => '']], 7]
    );

    $this->assertSame(1, $result['inserted']);
  }

  /**
   * @test
   * -# Associate the same (cpPk, licenseId) pair via two different entries.
   * -# Verify both mapping-existence checks share a single, constant
   *    prepared-statement name rather than one name per pair.
   *    Regression: embedding cpPk/licenseId in the statement name creates
   *    one server-side prepared statement per row on a large import.
   */
  public function testAssociateLicensesUsesConstantStatementNameForMappingCheck(): void
  {
    $seenNames = [];
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs(function ($sql, $params, $name) use (&$seenNames) {
        $seenNames[] = $name;
        return true;
      })
      ->andReturn(null);
    $this->dbManager->shouldReceive('insertTableRow')->twice();

    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('MIT', null)->andReturn($this->makeLicense(1));
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('Apache-2.0', null)->andReturn($this->makeLicense(2));

    Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'associateLicensesWithMetadata',
      [9, [
        ['shortname' => 'MIT', 'removing' => false, 'comment' => '', 'reportinfo' => '', 'acknowledgement' => ''],
        ['shortname' => 'Apache-2.0', 'removing' => false, 'comment' => '', 'reportinfo' => '', 'acknowledgement' => ''],
      ]]
    );

    $this->assertCount(1, array_unique($seenNames));
  }

  /**
   * @test
   * -# Associate a license that is already mapped to the phrase.
   * -# Verify it is counted as 'skipped', never 'inserted'.
   *    Regression: counting an already-existing mapping as "associated"
   *    made a duplicate re-import falsely report new licenses added.
   */
  public function testAssociateLicensesCountsExistingMappingAsSkippedNotInserted(): void
  {
    $this->dbManager->shouldReceive('getSingleRow')->andReturn(['exists' => 1]);
    $this->dbManager->shouldNotReceive('insertTableRow');

    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('MIT', null)->andReturn($this->makeLicense(1));

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'associateLicensesWithMetadata',
      [1, [['shortname' => 'MIT', 'removing' => false, 'comment' => '', 'reportinfo' => '', 'acknowledgement' => '']]]
    );

    $this->assertSame(0, $result['inserted']);
    $this->assertSame(1, $result['skipped']);
  }

  /**
   * @test
   * -# Associate a license whose metadata fields are entirely absent from the entry.
   * -# Verify no "Undefined array key" warning is triggered and NULLs are inserted.
   */
  public function testAssociateLicensesHandlesMissingMetadataKeysWithoutWarning(): void
  {
    $this->dbManager->shouldReceive('getSingleRow')->andReturn(null);
    $this->dbManager->shouldReceive('insertTableRow')
      ->once()
      ->with('custom_phrase_license_map', M::on(function ($row) {
        return $row['comment'] === null && $row['reportinfo'] === null && $row['acknowledgement'] === null;
      }));

    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('MIT', null)->andReturn($this->makeLicense(1));

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'associateLicensesWithMetadata',
      [1, [['shortname' => 'MIT']]] // no removing/comment/reportinfo/acknowledgement keys
    );

    $this->assertSame(1, $result['inserted']);
  }

  /**
   * @test
   * Regression: GPL-2.0-only must be looked up as-is, not mangled.
   */
  public function testAssociateLicenseNamesLooksUpSpdxNameDirectly(): void
  {
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('GPL-2.0-only', null)->once()->andReturn($this->makeLicense(7));
    $this->licenseDao->shouldNotReceive('insertLicense');

    $this->dbManager->shouldReceive('getSingleRow')->andReturn(null);
    $this->dbManager->shouldReceive('insertTableRow')->once();

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer, 'associateLicenseNames', [42, 'GPL-2.0-only', false]
    );

    $this->assertSame(1, $result['inserted']);
    $this->assertEmpty($result['failed']);
  }

  /**
   * @test
   * Comma-separated license list is split and each name looked up independently.
   */
  public function testAssociateLicenseNamesHandlesCommaSeparatedList(): void
  {
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('MIT', null)->once()->andReturn($this->makeLicense(1));
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('Apache-2.0', null)->once()->andReturn($this->makeLicense(2));

    $this->dbManager->shouldReceive('getSingleRow')->andReturn(null);
    $this->dbManager->shouldReceive('insertTableRow')->twice();

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer, 'associateLicenseNames', [46, 'MIT, Apache-2.0', false]
    );

    $this->assertSame(2, $result['inserted']);
  }

  /**
   * @test
   * An unknown license name is reported in 'failed', not silently dropped.
   */
  public function testAssociateLicenseNamesReportsUnknownLicense(): void
  {
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('NoSuchLicense', null)->once()->andReturn(null);

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer, 'associateLicenseNames', [1, 'NoSuchLicense', false]
    );

    $this->assertSame(0, $result['inserted']);
    $this->assertStringContainsString('unknown', $result['failed'][0]);
  }

  /**
   * @test
   * -# Call importSinglePhrase with an empty text field.
   * -# Verify it returns success=false without touching the DB.
   */
  public function testImportSinglePhraseRejectsEmptyText(): void
  {
    $this->dbManager->shouldNotReceive('getSingleRow');
    $this->dbManager->shouldNotReceive('insertPreparedAndReturn');

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer, 'importSinglePhrase', [['text' => '']]
    );

    $this->assertFalse($result['success']);
    $this->assertStringContainsStringIgnoringCase('required', $result['message']);
  }

  /**
   * @test
   * -# Call importSinglePhrase with 'text' as a JSON array instead of a string
   *    (e.g. a hand-edited or malformed import file).
   * -# Verify it returns a clean success=false result instead of an uncaught
   *    TypeError from md5(), and never opens a transaction.
   */
  public function testImportSinglePhraseRejectsArrayText(): void
  {
    $this->dbManager->shouldNotReceive('getSingleRow');
    $this->dbManager->shouldNotReceive('begin');

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer, 'importSinglePhrase', [['text' => ['not', 'a', 'string']]]
    );

    $this->assertFalse($result['success']);
    $this->assertStringContainsStringIgnoringCase('required', $result['message']);
  }

  /**
   * @test
   * -# One license entry carries an array 'shortname' instead of a string.
   * -# Verify the malformed entry is dropped (not passed to trim()) and the
   *    phrase itself still imports successfully instead of crashing.
   */
  public function testImportSinglePhraseSkipsLicenseEntryWithArrayShortname(): void
  {
    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/INSERT INTO custom_phrase/'), M::any(), M::any())
      ->once()->andReturn(['cp_pk' => 11]);
    $this->dbManager->shouldNotReceive('insertTableRow');
    $this->licenseDao->shouldNotReceive('getLicenseByShortName');

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'importSinglePhrase',
      [[
        'text' => 'phrase with a malformed license entry',
        'licenses' => [['shortname' => ['nested', 'array'], 'removing' => false]]
      ]]
    );

    $this->assertTrue($result['success']);
  }

  /**
   * @test
   * -# 'is_active' arrives as a JSON array instead of a boolean/string.
   * -# Verify parseBoolean() treats it as false instead of crashing trim().
   */
  public function testImportSinglePhraseTreatsArrayIsActiveAsFalse(): void
  {
    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/INSERT INTO custom_phrase/'), M::any(), M::any())
      ->once()
      ->withArgs(function ($sql, $params) {
        return $params[4] === 'false';
      })
      ->andReturn(['cp_pk' => 12]);

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer, 'importSinglePhrase', [['text' => 'malformed is_active', 'is_active' => ['x']]]
    );

    $this->assertTrue($result['success']);
  }

  /**
   * @test
   * -# Import a batch where row 2's text is an array (malformed).
   * -# Verify row 1 and row 3 still succeed and row 2 is reported as an
   *    error, instead of a crash aborting the whole batch.
   */
  public function testImportPhrasesOneMalformedRowDoesNotAbortRemainingRows(): void
  {
    $insertAttempt = 0;
    $this->dbManager->shouldReceive('getSingleRow')
      ->andReturnUsing(function ($sql) use (&$insertAttempt) {
        if (strpos($sql, 'INSERT INTO custom_phrase') !== false) {
          $insertAttempt++;
          return ['cp_pk' => $insertAttempt];
        }
        return null;
      });

    $data = [
      ['text' => 'row one valid'],
      ['text' => ['row', 'two', 'malformed']],
      ['text' => 'row three valid'],
    ];

    $msg = '';
    $result = $this->importer->importJsonData($data, $msg);

    $this->assertStringContainsString('2 phrase(s) created', $result);
    $this->assertStringContainsString('Row 2', $result);
  }

  /**
   * @test
   * -# Provide a unique phrase (no text_md5 match in DB).
   * -# Verify importSinglePhrase inserts the phrase and returns success=true, not existing/unchanged.
   */
  public function testImportSinglePhraseCreatesNewPhrase(): void
  {
    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/INSERT INTO custom_phrase/'), M::any(), M::any())
      ->once()->andReturn(['cp_pk' => 10]);

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer, 'importSinglePhrase', [['text' => 'brand new unique phrase']]
    );

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['existing'] ?? '');
    $this->assertEmpty($result['unchanged'] ?? '');
  }

  /**
   * @test
   * -# Simulate a duplicate text (existing row in custom_phrase).
   * -# Include a license not yet mapped to that phrase.
   * -# Verify the license is associated with the existing phrase (success=true, existing=true).
   */
  public function testImportSinglePhraseOnDuplicateTextWithNewLicenseAddsToExisting(): void
  {
    $this->dbManager->shouldReceive('getSingleRow')
      ->andReturnUsing(function ($sql) {
        if (strpos($sql, 'INSERT INTO custom_phrase') !== false) {
          return false; // ON CONFLICT: text already exists
        }
        if (strpos($sql, 'text_md5') !== false) {
          return ['cp_pk' => 7]; // find-existing fallback
        }
        return null; // license map check - not yet associated
      });
    $this->dbManager->shouldReceive('insertTableRow')->once();
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('Apache-2.0', 1)->once()->andReturn($this->makeLicense(20));

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'importSinglePhrase',
      [[
        'text' => 'existing phrase',
        'licenses' => [['shortname' => 'Apache-2.0', 'removing' => false,
                        'comment' => '', 'reportinfo' => '', 'acknowledgement' => '']]
      ]]
    );

    $this->assertTrue($result['success']);
    $this->assertTrue($result['existing']);
  }

  /**
   * @test
   * -# Simulate a duplicate text whose only license is ALREADY mapped.
   * -# Verify the row is reported as success=true, unchanged=true (not an error).
   *    This is the fix for re-importing the same export twice: it must be a
   *    safe no-op, not misreported as either a failure or a fresh update.
   */
  public function testImportSinglePhraseOnDuplicateTextWithAlreadyMappedLicenseIsUnchanged(): void
  {
    $this->dbManager->shouldReceive('getSingleRow')
      ->andReturnUsing(function ($sql) {
        if (strpos($sql, 'INSERT INTO custom_phrase') !== false) {
          return false; // ON CONFLICT: text already exists
        }
        if (strpos($sql, 'text_md5') !== false) {
          return ['cp_pk' => 7]; // find-existing fallback
        }
        return ['exists' => 1]; // license already mapped
      });
    $this->dbManager->shouldNotReceive('insertTableRow');
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('MIT', 1)->once()->andReturn($this->makeLicense(5));

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'importSinglePhrase',
      [[
        'text' => 'existing phrase',
        'licenses' => [['shortname' => 'MIT', 'removing' => false,
                        'comment' => '', 'reportinfo' => '', 'acknowledgement' => '']]
      ]]
    );

    $this->assertTrue($result['success']);
    $this->assertTrue($result['unchanged']);
    $this->assertArrayNotHasKey('existing', $result);
  }

  /**
   * @test
   * -# Simulate a duplicate text with no license payload at all.
   * -# Verify success=true, unchanged=true (not a failure).
   */
  public function testImportSinglePhraseOnDuplicateTextWithNoLicenseIsUnchanged(): void
  {
    $this->dbManager->shouldReceive('getSingleRow')
      ->andReturnUsing(function ($sql) {
        if (strpos($sql, 'INSERT INTO custom_phrase') !== false) {
          return false; // ON CONFLICT: text already exists
        }
        return ['cp_pk' => 3]; // find-existing fallback
      });

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer, 'importSinglePhrase', [['text' => 'existing phrase, no new license']]
    );

    $this->assertTrue($result['success']);
    $this->assertTrue($result['unchanged']);
  }

  /**
   * @test
   * -# Simulate a duplicate text whose license entry fails to resolve (unknown license).
   * -# Verify success=false with the unknown license named in the message.
   */
  public function testImportSinglePhraseOnDuplicateTextWithUnknownLicenseFails(): void
  {
    $this->dbManager->shouldReceive('getSingleRow')
      ->andReturnUsing(function ($sql) {
        if (strpos($sql, 'INSERT INTO custom_phrase') !== false) {
          return false; // ON CONFLICT: text already exists
        }
        return ['cp_pk' => 3]; // find-existing fallback
      });
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('NoSuchLicense', 1)->once()->andReturn(null);

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->importer,
      'importSinglePhrase',
      [[
        'text' => 'existing phrase',
        'licenses' => [['shortname' => 'NoSuchLicense', 'removing' => false,
                        'comment' => '', 'reportinfo' => '', 'acknowledgement' => '']]
      ]]
    );

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('NoSuchLicense', $result['message']);
  }

  /**
   * @test
   * -# Import two rows that share the same text but carry different, not-yet-mapped licenses.
   * -# Row 1: new phrase created + MIT associated.
   * -# Row 2: same text, existing phrase, Apache-2.0 added.
   * -# Verify message is "1 phrase(s) created, 1 license(s) added to existing phrase(s)".
   */
  public function testImportPhrasesProducesCorrectCountMessage(): void
  {
    $insertAttempt = 0;
    $this->dbManager->shouldReceive('getSingleRow')
      ->andReturnUsing(function ($sql) use (&$insertAttempt) {
        if (strpos($sql, 'INSERT INTO custom_phrase') !== false) {
          $insertAttempt++;
          return $insertAttempt === 1 ? ['cp_pk' => 1] : false;
        }
        if (strpos($sql, 'text_md5') !== false) {
          return ['cp_pk' => 1]; // find-existing fallback for row 2
        }
        return null; // license map checks - nothing mapped yet
      });
    $this->dbManager->shouldReceive('insertTableRow')->twice();

    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('MIT', 1)->andReturn($this->makeLicense(5));
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('Apache-2.0', 1)->andReturn($this->makeLicense(6));

    $data = [
      ['text' => 'shared phrase', 'licenses' =>
        [['shortname' => 'MIT', 'removing' => false,
          'comment' => '', 'reportinfo' => '', 'acknowledgement' => '']]],
      ['text' => 'shared phrase', 'licenses' =>
        [['shortname' => 'Apache-2.0', 'removing' => false,
          'comment' => '', 'reportinfo' => '', 'acknowledgement' => '']]],
    ];

    $msg = '';
    $result = $this->importer->importJsonData($data, $msg);

    $this->assertStringContainsString('1 phrase(s) created', $result);
    $this->assertStringContainsString('1 license(s) added to existing phrase(s)', $result);
  }

  /**
   * @test
   * -# Import one row where the text already exists and carries no new licenses.
   * -# Verify the result reports it under "already up to date", not as an error,
   *    and the overall summary still reads "nothing new to import" isn't used
   *    once there is at least one unchanged row to report.
   */
  public function testImportPhrasesReportsUnchangedRowsSeparately(): void
  {
    $this->dbManager->shouldReceive('getSingleRow')
      ->andReturnUsing(function ($sql) {
        if (strpos($sql, 'INSERT INTO custom_phrase') !== false) {
          return false; // ON CONFLICT: text already exists
        }
        return ['cp_pk' => 5]; // find-existing fallback
      });

    $data = [['text' => 'already exists, no license']];
    $msg = '';
    $result = $this->importer->importJsonData($data, $msg);

    $this->assertStringContainsString('1 row(s) already up to date', $result);
    $this->assertStringNotContainsString('Errors:', $result);
  }

  /**
   * @test
   * -# Import an empty data array.
   * -# Verify the message says "nothing new to import".
   */
  public function testImportPhrasesEmptyDataReportsNothingNewToImport(): void
  {
    $msg = '';
    $result = $this->importer->importJsonData([], $msg);

    $this->assertStringContainsString('nothing new to import', $result);
  }

  /**
   * @test
   * -# Import a mix: one valid new phrase and one row with empty text.
   * -# Verify the message includes "1 phrase(s) created" AND an error for the empty row.
   */
  public function testImportPhrasesReportsErrorRowsAlongSideSuccesses(): void
  {
    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/INSERT INTO custom_phrase/'), M::any(), M::any())
      ->once()->andReturn(['cp_pk' => 2]);

    $data = [
      ['text' => 'valid phrase'],
      ['text' => ''],
    ];

    $msg = '';
    $result = $this->importer->importJsonData($data, $msg);

    $this->assertStringContainsString('1 phrase(s) created', $result);
    $this->assertStringContainsString('Row 2', $result);
  }

  /**
   * @test
   * -# Re-import the exact same single-phrase, single-license dataset twice
   *    in a row (simulating "download export, re-upload unmodified").
   * -# Verify the second import reports 1 unchanged row and 0 created/updated.
   */
  public function testReimportingSameDataIsIdempotentAndReportedAsUnchanged(): void
  {
    // First import: phrase does not exist yet.
    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/INSERT INTO custom_phrase/'), M::any(), M::any())
      ->once()
      ->andReturn(['cp_pk' => 1]);
    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/custom_phrase_license_map/'), M::any(), M::any())
      ->once()
      ->andReturn(null);
    $this->dbManager->shouldReceive('insertTableRow')->once();
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('MIT', 1)->twice()->andReturn($this->makeLicense(1));

    $data = [['text' => 'idempotent phrase', 'licenses' =>
      [['shortname' => 'MIT', 'removing' => false, 'comment' => '', 'reportinfo' => '', 'acknowledgement' => '']]]];

    $msg1 = '';
    $result1 = $this->importer->importJsonData($data, $msg1);
    $this->assertStringContainsString('1 phrase(s) created', $result1);

    // Second import: phrase exists now, and MIT is already mapped.
    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/INSERT INTO custom_phrase/'), M::any(), M::any())
      ->once()
      ->andReturn(false);
    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/^SELECT cp_pk FROM custom_phrase WHERE text_md5/'), M::any(), M::any())
      ->once()
      ->andReturn(['cp_pk' => 1]);
    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/custom_phrase_license_map/'), M::any(), M::any())
      ->once()
      ->andReturn(['exists' => 1]);

    $msg2 = '';
    $result2 = $this->importer->importJsonData($data, $msg2);

    $this->assertStringContainsString('1 row(s) already up to date', $result2);
    $this->assertStringNotContainsString('phrase(s) created', $result2);
    $this->assertStringNotContainsString('license(s) added', $result2);
  }

  /**
   * @test
   * -# Write a UTF-8 BOM CSV file using the exact header format from BulkTextExport.
   * -# Import via handleFile() and verify one phrase is created.
   */
  public function testHandleCsvFileCreatesSinglePhrase(): void
  {
    $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
    $csv = $bom . "Text,Is Active,License Shortname,Removing,Comment,License Text,Acknowledgement\n";
    $csv .= '"simple scanning phrase",true,MIT,false,,,';

    $path = $this->writeTempFile($csv, 'csv');

    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/INSERT INTO custom_phrase/'), M::any(), M::any())
      ->once()->andReturn(['cp_pk' => 1]);
    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/custom_phrase_license_map/'), M::any(), M::any())
      ->once()->andReturn(null);
    $this->dbManager->shouldReceive('insertTableRow')->once();

    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('MIT', 1)->once()->andReturn($this->makeLicense(3));

    $result = $this->importer->handleFile($path, 'csv');

    $this->assertStringContainsString('1 phrase(s) created', $result);
  }

  /**
   * @test
   * Regression for #3776: the "Tab" option in the import UI's delimiter
   * <select> used value="\t", which HTML does not decode as an escape
   * sequence -- it submitted the literal two characters "\" and "t". That
   * string then got truncated by setDelimiter() to just "\", so choosing
   * "Tab" silently parsed uploads with a backslash delimiter instead of a
   * tab. The template now emits value="&#9;", a numeric character
   * reference that browsers do decode to a real tab byte.
   * -# Call setDelimiter() with a real tab byte, exactly as the fixed
   *    template now submits it.
   * -# Write a tab-delimited CSV file (no comma/semicolon in it).
   * -# Import via handleFile() and verify one phrase is created, proving
   *    the row was actually split into columns rather than read as one
   *    unparsed blob.
   */
  public function testHandleCsvFileWithTabDelimiterCreatesSinglePhrase(): void
  {
    $this->importer->setDelimiter("\t");

    $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
    $csv = $bom . "Text\tIs Active\tLicense Shortname\tRemoving\tComment\tLicense Text\tAcknowledgement\n";
    $csv .= "\"tab delimited phrase\"\ttrue\tMIT\tfalse\t\t\t";

    $path = $this->writeTempFile($csv, 'csv');

    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/INSERT INTO custom_phrase/'), M::any(), M::any())
      ->once()->andReturn(['cp_pk' => 1]);
    $this->dbManager->shouldReceive('getSingleRow')
      ->with(M::pattern('/custom_phrase_license_map/'), M::any(), M::any())
      ->once()->andReturn(null);
    $this->dbManager->shouldReceive('insertTableRow')->once();

    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('MIT', 1)->once()->andReturn($this->makeLicense(3));

    $result = $this->importer->handleFile($path, 'csv');

    $this->assertStringContainsString('1 phrase(s) created', $result);
  }

  /**
   * @test
   * Documents the pre-existing truncation behavior of setDelimiter() that
   * made the #3776 bug possible: a multi-character string is silently cut
   * to its first byte rather than rejected. Before the template fix, the
   * browser sent exactly this "\t" two-character string for the "Tab"
   * option, which truncated to "\".
   * -# Call setDelimiter() with the literal two-character string "\t".
   * -# Verify the stored delimiter is just the backslash.
   */
  public function testSetDelimiterTruncatesMultiCharStringToFirstByte(): void
  {
    $this->importer->setDelimiter('\\t');

    $delimiter = Reflectory::getObjectsProperty($this->importer, 'delimiter');

    $this->assertSame('\\', $delimiter);
  }

  /**
   * @test
   * -# Write a CSV with 3 rows sharing the same text (one phrase, 3 licenses).
   *    This mirrors the output of BulkTextExport for a phrase with 3 license mappings.
   * -# Row 1 creates the phrase and associates GPL-2.0-or-later.
   * -# Rows 2 and 3 detect the duplicate text and add LGPL-2.1-or-later / MPL-1.1+.
   * -# Verify message: "1 phrase(s) created, 2 license(s) added to existing phrase(s)".
   */
  public function testHandleCsvFileMultiLicenseSameTextUpdatesExisting(): void
  {
    $text = "Licensed under any of the following licenses.";
    $csv = "Text,Is Active,License Shortname,Removing,Comment,License Text,Acknowledgement\n";
    $csv .= "\"$text\",true,GPL-2.0-or-later,false,,,\n";
    $csv .= "\"$text\",true,LGPL-2.1-or-later,true,,,\n";
    $csv .= "\"$text\",true,MPL-1.1+,true,,,\n";

    $path = $this->writeTempFile($csv, 'csv');

    $insertAttempt = 0;
    $this->dbManager->shouldReceive('getSingleRow')
      ->andReturnUsing(function ($sql) use (&$insertAttempt) {
        if (strpos($sql, 'INSERT INTO custom_phrase') !== false) {
          $insertAttempt++;
          return $insertAttempt === 1 ? ['cp_pk' => 99] : false;
        }
        if (strpos($sql, 'text_md5') !== false) {
          return ['cp_pk' => 99]; // find-existing fallback for rows 2 and 3
        }
        return null; // license map checks - nothing mapped yet
      });
    $this->dbManager->shouldReceive('insertTableRow')->times(3);

    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('GPL-2.0-or-later', 1)->andReturn($this->makeLicense(10));
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('LGPL-2.1-or-later', 1)->andReturn($this->makeLicense(11));
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('MPL-1.1+', 1)->andReturn($this->makeLicense(12));

    $result = $this->importer->handleFile($path, 'csv');

    $this->assertStringContainsString('1 phrase(s) created', $result);
    $this->assertStringContainsString('2 license(s) added to existing phrase(s)', $result);
  }

  /**
   * @test
   * -# Write a CSV where a data row has fewer columns than the header.
   * -# Verify handleFile returns a column-count error message without importing.
   */
  public function testHandleCsvFileRejectsColumnCountMismatch(): void
  {
    $csv = "Text,Is Active,License Shortname\n";
    $csv .= "only two columns,true\n";

    $path = $this->writeTempFile($csv, 'csv');

    $this->dbManager->shouldNotReceive('insertPreparedAndReturn');

    $result = $this->importer->handleFile($path, 'csv');

    $this->assertStringContainsStringIgnoringCase('column', $result);
  }

  /**
   * @test
   * -# Write a CSV where the phrase text contains a literal backslash-n
   *    two-character sequence (as BulkTextExport would have flattened a
   *    real newline into).
   * -# Verify the imported phrase text has a real newline, not '\n' text.
   */
  public function testHandleCsvFileUnescapesNewlinesInText(): void
  {
    $csv = "Text,Is Active,License Shortname,Removing,Comment,License Text,Acknowledgement\n";
    $csv .= "\"line one\\nline two\",true,MIT,false,,,\n";

    $path = $this->writeTempFile($csv, 'csv');

    $capturedText = null;
    $this->dbManager->shouldReceive('getSingleRow')
      ->andReturnUsing(function ($sql, $params) use (&$capturedText) {
        if (strpos($sql, 'INSERT INTO custom_phrase') !== false) {
          $capturedText = $params[0];
          return ['cp_pk' => 1];
        }
        return null; // license map check - not yet associated
      });
    $this->dbManager->shouldReceive('insertTableRow')->once();
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('MIT', 1)->once()->andReturn($this->makeLicense(1));

    $this->importer->handleFile($path, 'csv');

    $this->assertSame("line one\nline two", $capturedText);
  }

  /**
   * @test
   * -# Write a JSON file with two independent phrases in nested licenses[] format.
   * -# Verify handleFile creates 2 phrases.
   */
  public function testHandleJsonFileCreatesTwoPhrases(): void
  {
    $data = [
      ['text' => 'first phrase', 'is_active' => true, 'licenses' =>
        [['shortname' => 'MIT', 'removing' => false,
          'comment' => '', 'reportinfo' => '', 'acknowledgement' => '']]],
      ['text' => 'second phrase', 'is_active' => true, 'licenses' => []],
    ];

    $path = $this->writeTempFile(json_encode($data), 'json');

    $insertAttempt = 0;
    $this->dbManager->shouldReceive('getSingleRow')
      ->andReturnUsing(function ($sql) use (&$insertAttempt) {
        if (strpos($sql, 'INSERT INTO custom_phrase') !== false) {
          $insertAttempt++;
          return ['cp_pk' => $insertAttempt];
        }
        return null; // license map check - not yet associated
      });
    $this->dbManager->shouldReceive('insertTableRow')->once();

    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('MIT', 1)->once()->andReturn($this->makeLicense(7));

    $result = $this->importer->handleFile($path, 'json');

    $this->assertStringContainsString('2 phrase(s) created', $result);
  }

  /**
   * @test
   * -# Write a JSON file whose content is not valid JSON.
   * -# Verify handleFile returns an error message containing "Invalid JSON".
   */
  public function testHandleJsonFileRejectsInvalidJson(): void
  {
    $path = $this->writeTempFile('{not valid json[', 'json');

    $result = $this->importer->handleFile($path, 'json');

    $this->assertStringContainsStringIgnoringCase('invalid json', $result);
  }

  /**
   * @test
   * -# Write a JSON file whose top-level value is a scalar (not an array).
   * -# Verify handleFile returns a "must contain an array" error.
   */
  public function testHandleJsonFileRejectsNonArrayRoot(): void
  {
    $path = $this->writeTempFile('"just a string"', 'json');

    $result = $this->importer->handleFile($path, 'json');

    $this->assertStringContainsStringIgnoringCase('array', $result);
  }

  /**
   * @test
   * -# Import a JSON phrase where the same text appears twice in the array
   *    but row 2 carries a new license.
   * -# Verify: 1 phrase(s) created, 1 license(s) added to existing phrase(s).
   */
  public function testHandleJsonFileSameTextTwiceAddsLicenseToExisting(): void
  {
    $data = [
      ['text' => 'repeat phrase', 'licenses' =>
        [['shortname' => 'MIT', 'removing' => false,
          'comment' => '', 'reportinfo' => '', 'acknowledgement' => '']]],
      ['text' => 'repeat phrase', 'licenses' =>
        [['shortname' => 'Apache-2.0', 'removing' => false,
          'comment' => '', 'reportinfo' => '', 'acknowledgement' => '']]],
    ];

    $path = $this->writeTempFile(json_encode($data), 'json');

    $insertAttempt = 0;
    $this->dbManager->shouldReceive('getSingleRow')
      ->andReturnUsing(function ($sql) use (&$insertAttempt) {
        if (strpos($sql, 'INSERT INTO custom_phrase') !== false) {
          $insertAttempt++;
          return $insertAttempt === 1 ? ['cp_pk' => 20] : false;
        }
        if (strpos($sql, 'text_md5') !== false) {
          return ['cp_pk' => 20]; // find-existing fallback for row 2
        }
        return null;
      });
    $this->dbManager->shouldReceive('insertTableRow')->twice();

    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('MIT', 1)->andReturn($this->makeLicense(1));
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->with('Apache-2.0', 1)->andReturn($this->makeLicense(2));

    $result = $this->importer->handleFile($path, 'json');

    $this->assertStringContainsString('1 phrase(s) created', $result);
    $this->assertStringContainsString('1 license(s) added to existing phrase(s)', $result);
  }
}
