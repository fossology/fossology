<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Srac\Test\Unit;

use Fossology\Srac\SracParser;
use Fossology\Srac\SracValidator;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../agent/SracParser.php';
require_once __DIR__ . '/../../agent/SracValidator.php';

class SracValidatorTest extends TestCase
{
  /** @var SracValidator */
  private $validator;

  /** @var SracParser */
  private $parser;

  protected function setUp(): void
  {
    $this->validator = new SracValidator();
    $this->parser = new SracParser();
  }

  public function testValidAssertionPassesValidation()
  {
    $doc = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-srac.json');
    $errors = $this->validator->validateAssertion($doc);
    $this->assertEmpty($errors, "Expected valid-srac.json to have 0 validation errors, got: " . implode('; ', $errors));
  }

  public function testCommunicationSracPassesValidation()
  {
    $doc = $this->parser->parseFile(__DIR__ . '/../fixtures/communication.srac.json');
    $errors = $this->validator->validateAssertion($doc);
    $this->assertEmpty($errors, "Expected communication.srac.json to have 0 validation errors, got: " . implode('; ', $errors));
  }

  public function testInvalidAssertionReportsExplicitErrors()
  {
    $doc = $this->parser->parseFile(__DIR__ . '/../fixtures/invalid-srac.json');
    $errors = $this->validator->validateAssertion($doc);
    $this->assertNotEmpty($errors);

    $joined = implode('; ', $errors);
    // Checks for bad status enum
    $this->assertStringContainsString('safetyRelevance.status', $joined);
    // Checks for duplicate element ID
    $this->assertStringContainsString('defined more than once', $joined);
    // Checks for unresolved internal reference
    $this->assertStringContainsString('does not resolve to an assertion element', $joined);
    // Checks for safetyIntegrityLevel mismatch
    $this->assertStringContainsString('safetyIntegrityLevel', $joined);
  }

  public function testMissingSchemaVersion()
  {
    $doc = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-srac.json');
    unset($doc['schemaVersion']);
    $errors = $this->validator->validateAssertion($doc);
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString("schemaVersion must be '0.2-draft'", implode('; ', $errors));
  }

  public function testInvalidPurlFormat()
  {
    $doc = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-srac.json');
    $doc['subject']['purl'] = 'invalid-purl-format';
    $errors = $this->validator->validateAssertion($doc);
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString("subject.purl must start with 'pkg:'", implode('; ', $errors));
  }

  public function testInvalidCreatedDateTime()
  {
    $doc = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-srac.json');
    $doc['assertion']['created'] = 'not-a-date';
    $errors = $this->validator->validateAssertion($doc);
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('assertion.created must be a valid ISO 8601 date-time string', implode('; ', $errors));
  }

  public function testSourceOfTruthInvalidSystem()
  {
    $doc = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-srac.json');
    $doc['sourceOfTruth'] = [
      'system' => 'unsupported-system',
      'documentUri' => 'https://example.org/doc',
      'version' => '1.0',
      'rootElement' => 'root',
      'rootElementType' => 'type',
      'rootElementStatus' => 'active',
      'includedElements' => ['el1'],
      'sha256' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
    ];
    $errors = $this->validator->validateAssertion($doc);
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString("sourceOfTruth.system must be 'sphinx-needs'", implode('; ', $errors));
  }
}
