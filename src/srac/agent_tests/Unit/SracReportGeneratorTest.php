<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Srac\Test\Unit;

use Fossology\Srac\SracCorrelator;
use Fossology\Srac\SracParser;
use Fossology\Srac\SracReportGenerator;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../agent/SracParser.php';
require_once __DIR__ . '/../../agent/SracValidator.php';
require_once __DIR__ . '/../../agent/SracResult.php';
require_once __DIR__ . '/../../agent/SracCorrelator.php';
require_once __DIR__ . '/../../agent/SracReportGenerator.php';

class SracReportGeneratorTest extends TestCase
{
  /** @var SracReportGenerator */
  private $reportGenerator;

  /** @var SracCorrelator */
  private $correlator;

  /** @var SracParser */
  private $parser;

  protected function setUp(): void
  {
    $this->parser = new SracParser();
    $this->correlator = new SracCorrelator();
    $this->reportGenerator = new SracReportGenerator();
  }

  public function testGenerateSingleReportConformsToSchema()
  {
    $srac = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-srac.json');
    $spdx = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-spdx.json');

    $integrity = [
      'algorithm' => 'SHA-256',
      'assertionSha256' => '4b52a2ec6712866fa5de7dbfd14e780c0d1df71bdfcdbb24f0ea3aec48d88d2c',
      'sbomSha256' => 'e147f5db23d35c97c8c779c217d6edb9977d01cddf7683e2c46d45cf09737243'
    ];

    $result = $this->correlator->correlateAssertion($srac, $spdx, $integrity);
    $report = $this->reportGenerator->generateSingleReport($result, 'SPDX', $integrity);

    $this->assertEquals('0.2-draft', $report['schemaVersion']);
    $this->assertEquals('SPDX', $report['sourceFormat']);
    $this->assertEquals('srac-synthetic-brake-monitor-001', $report['assertionId']);
    $this->assertEquals('matched', $report['matchStatus']);
    $this->assertEquals('assertion', $report['safetyAssessment']['source']);
    $this->assertEquals('safety-related', $report['safetyAssessment']['safetyRelevance']);
    $this->assertEquals('ASIL-B', $report['safetyAssessment']['classification']);
    $this->assertEquals('reviewed', $report['safetyAssessment']['assertionStatus']);
    $this->assertEquals('fossology.srac', $report['generator']['name']);
    $this->assertNotEmpty($report['matchedComponents']);
    $this->assertEquals('SPDXRef-Package-brake-monitor', $report['matchedComponents'][0]['identifier']);
  }

  public function testGenerateComprehensiveReportCalculatesSummary()
  {
    $sracMatched = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-srac.json');
    $sracUnmatched = $this->parser->parseFile(__DIR__ . '/../fixtures/unmatched-srac.json');
    $sracInvalid = $this->parser->parseFile(__DIR__ . '/../fixtures/invalid-srac.json');
    $spdx = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-spdx.json');

    $res1 = $this->correlator->correlateAssertion($sracMatched, $spdx);
    $res2 = $this->correlator->correlateAssertion($sracUnmatched, $spdx);
    $res3 = $this->correlator->correlateAssertion($sracInvalid, $spdx);

    $report = $this->reportGenerator->generateComprehensiveReport([$res1, $res2, $res3], 'SPDX');

    $this->assertEquals(3, $report['summary']['totalAssertions']);
    $this->assertEquals(1, $report['summary']['matched']);
    $this->assertEquals(1, $report['summary']['unmatched']);
    $this->assertEquals(1, $report['summary']['invalid']);
    $this->assertEquals(0, $report['summary']['ambiguous']);
    $this->assertStringContainsString('FOSSology correlates and reports externally authored safety assertions', $report['generator']['notice']);
  }

  public function testGenerateTextReportOutputsSummaryAndDetails()
  {
    $srac = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-srac.json');
    $spdx = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-spdx.json');

    $res = $this->correlator->correlateAssertion($srac, $spdx);
    $comprehensive = $this->reportGenerator->generateComprehensiveReport([$res], 'SPDX');
    $text = $this->reportGenerator->generateTextReport($comprehensive);

    $this->assertStringContainsString('FOSSOLOGY SRAC CORRELATION AUDIT REPORT', $text);
    $this->assertStringContainsString('Total Assertions: 1', $text);
    $this->assertStringContainsString('Matched:          1', $text);
    $this->assertStringContainsString('srac-synthetic-brake-monitor-001', $text);
    $this->assertStringContainsString('SPDXRef-Package-brake-monitor', $text);
  }
}
