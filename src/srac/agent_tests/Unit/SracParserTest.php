<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Srac\Test\Unit;

use Fossology\Srac\SracParser;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../agent/SracParser.php';

class SracParserTest extends TestCase
{
  /** @var SracParser */
  private $parser;

  protected function setUp(): void
  {
    $this->parser = new SracParser();
  }

  public function testParseValidJsonString()
  {
    $json = '{"schemaVersion": "0.2-draft", "assertionId": "test-1"}';
    $result = $this->parser->parseString($json);
    $this->assertIsArray($result);
    $this->assertEquals('0.2-draft', $result['schemaVersion']);
    $this->assertEquals('test-1', $result['assertionId']);
  }

  public function testParseStringWithUtf8Bom()
  {
    $json = "\xEF\xBB\xBF" . '{"assertionId": "bom-test"}';
    $result = $this->parser->parseString($json);
    $this->assertEquals('bom-test', $result['assertionId']);
  }

  public function testParseEmptyStringThrowsException()
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Empty input provided');
    $this->parser->parseString('   ');
  }

  public function testParseMalformedJsonThrowsException()
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Malformed JSON');
    $this->parser->parseString('{ invalid: json }');
  }

  public function testParseScalarJsonThrowsException()
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Expected JSON object or array at root');
    $this->parser->parseString('"scalar string"');
  }

  public function testParseNonExistentFileThrowsException()
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('File does not exist');
    $this->parser->parseFile('/path/to/non/existent/file.json');
  }

  public function testParseValidFixtureFile()
  {
    $fixturePath = __DIR__ . '/../fixtures/valid-srac.json';
    $result = $this->parser->parseFile($fixturePath);
    $this->assertIsArray($result);
    $this->assertEquals('srac-synthetic-brake-monitor-001', $result['assertionId']);
  }
}
