<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Srac\Test\Unit;

use Fossology\Srac\SracCorrelator;
use Fossology\Srac\SracParser;
use Fossology\Srac\SracResult;
use Fossology\Srac\SracValidator;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../agent/SracParser.php';
require_once __DIR__ . '/../../agent/SracValidator.php';
require_once __DIR__ . '/../../agent/SracResult.php';
require_once __DIR__ . '/../../agent/SracCorrelator.php';

class SracCorrelatorTest extends TestCase
{
  /** @var SracCorrelator */
  private $correlator;

  /** @var SracParser */
  private $parser;

  protected function setUp(): void
  {
    $this->parser = new SracParser();
    $this->correlator = new SracCorrelator();
  }

  public function testValidSpdxExactMatch()
  {
    $srac = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-srac.json');
    $spdx = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-spdx.json');

    $result = $this->correlator->correlateAssertion($srac, $spdx);

    $this->assertEquals(SracResult::STATUS_MATCHED, $result->getStatus());
    $this->assertEquals(SracResult::RULE_PURL_VERSION, $result->getRule());
    $this->assertCount(1, $result->getMatchedComponents());
    $this->assertEquals('SPDXRef-Package-brake-monitor', $result->getMatchedComponents()[0]['identifier']);
    $this->assertEquals('synthetic-brake-monitor', $result->getMatchedComponents()[0]['name']);
  }

  public function testValidCycloneDxExactMatch()
  {
    $srac = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-srac.json');
    $cdx = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-cyclonedx.json');

    $result = $this->correlator->correlateAssertion($srac, $cdx);

    $this->assertEquals(SracResult::STATUS_MATCHED, $result->getStatus());
    $this->assertEquals(SracResult::RULE_PURL_VERSION, $result->getRule());
    $this->assertCount(1, $result->getMatchedComponents());
    $this->assertEquals('brake-monitor-ref', $result->getMatchedComponents()[0]['identifier']);
  }

  public function testCommunicationExampleSpdxMatch()
  {
    $srac = $this->parser->parseFile(__DIR__ . '/../fixtures/communication.srac.json');
    $spdx = $this->parser->parseFile(__DIR__ . '/../fixtures/communication.spdx.json');

    $result = $this->correlator->correlateAssertion($srac, $spdx);

    $this->assertEquals(SracResult::STATUS_MATCHED, $result->getStatus());
    $this->assertEquals('SPDXRef-score-communication-configuration', $result->getMatchedComponents()[0]['identifier']);
  }

  public function testCommunicationExampleCycloneDxMatch()
  {
    $srac = $this->parser->parseFile(__DIR__ . '/../fixtures/communication.srac.json');
    $cdx = $this->parser->parseFile(__DIR__ . '/../fixtures/communication.cdx.json');

    $result = $this->correlator->correlateAssertion($srac, $cdx);

    $this->assertEquals(SracResult::STATUS_MATCHED, $result->getStatus());
    $this->assertEquals('score-communication-configuration', $result->getMatchedComponents()[0]['identifier']);
  }

  public function testArtifactDigestMatch()
  {
    $srac = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-srac.json');
    $spdx = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-spdx.json');

    // Give subject a commit hash version and provide checksum on package
    $digest = '313f1178628189d267c8cd0d75971bdac04c20ee';
    $srac['subject']['version'] = $digest;
    $srac['subject']['purl'] = "pkg:generic/other-purl@1.0.0"; // PURL does not match
    $spdx['packages'][0]['checksums'] = [
      ['algorithm' => 'SHA-1', 'checksumValue' => $digest]
    ];

    $result = $this->correlator->correlateAssertion($srac, $spdx);

    $this->assertEquals(SracResult::STATUS_MATCHED, $result->getStatus());
    $this->assertEquals(SracResult::RULE_ARTIFACT_DIGEST, $result->getRule());
  }

  public function testSbomIdentifierMatch()
  {
    $srac = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-srac.json');
    $spdx = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-spdx.json');

    // Mismatch PURL and version
    $srac['subject']['purl'] = 'pkg:generic/different-purl@9.9.9';
    $srac['subject']['version'] = '9.9.9';
    // Match by componentPath matching SPDXID
    $srac['subject']['componentPath'] = 'SPDXRef-Package-brake-monitor';

    $result = $this->correlator->correlateAssertion($srac, $spdx);

    $this->assertEquals(SracResult::STATUS_MATCHED, $result->getStatus());
    $this->assertEquals(SracResult::RULE_SBOM_IDENTIFIER, $result->getRule());
  }

  public function testUnmatchedComponent()
  {
    $srac = $this->parser->parseFile(__DIR__ . '/../fixtures/unmatched-srac.json');
    $spdx = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-spdx.json');

    $result = $this->correlator->correlateAssertion($srac, $spdx);

    $this->assertEquals(SracResult::STATUS_UNMATCHED, $result->getStatus());
    $this->assertEquals(SracResult::RULE_NONE, $result->getRule());
    $this->assertEmpty($result->getMatchedComponents());
  }

  public function testVersionMismatchDoesNotMatch()
  {
    $srac = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-srac.json');
    $spdx = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-spdx.json');

    // Same base PURL but different version
    $srac['subject']['version'] = '2.0.0';
    $srac['subject']['purl'] = 'pkg:generic/synthetic-brake-monitor@2.0.0';

    $result = $this->correlator->correlateAssertion($srac, $spdx);

    $this->assertEquals(SracResult::STATUS_UNMATCHED, $result->getStatus());
    $this->assertEmpty($result->getMatchedComponents());
  }

  public function testNameOnlyMatchProhibited()
  {
    $srac = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-srac.json');
    $spdx = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-spdx.json');

    // Subject has matching name "synthetic-brake-monitor", but PURL and version differ
    $srac['subject']['name'] = 'synthetic-brake-monitor';
    $srac['subject']['purl'] = 'pkg:generic/completely-different@3.0.0';
    $srac['subject']['version'] = '3.0.0';
    $srac['subject']['componentPath'] = 'unrelated/path';

    $result = $this->correlator->correlateAssertion($srac, $spdx);

    // INVARIANT: Must NOT match on name alone!
    $this->assertEquals(SracResult::STATUS_UNMATCHED, $result->getStatus());
    $this->assertEmpty($result->getMatchedComponents());
  }

  public function testAmbiguousDuplicateCandidates()
  {
    $srac = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-srac.json');
    $spdx = $this->parser->parseFile(__DIR__ . '/../fixtures/ambiguous-spdx.json');

    $result = $this->correlator->correlateAssertion($srac, $spdx);

    // INVARIANT: When multiple candidates match, mark ambiguous and return all candidates without auto-selection
    $this->assertEquals(SracResult::STATUS_AMBIGUOUS, $result->getStatus());
    $this->assertEquals(SracResult::RULE_PURL_VERSION, $result->getRule());
    $this->assertCount(2, $result->getMatchedComponents());
    $this->assertEquals('SPDXRef-Duplicate-1', $result->getMatchedComponents()[0]['identifier']);
    $this->assertEquals('SPDXRef-Duplicate-2', $result->getMatchedComponents()[1]['identifier']);
  }

  public function testInvalidSracYieldsInvalidStatus()
  {
    $srac = $this->parser->parseFile(__DIR__ . '/../fixtures/invalid-srac.json');
    $spdx = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-spdx.json');

    $result = $this->correlator->correlateAssertion($srac, $spdx);

    $this->assertEquals(SracResult::STATUS_INVALID, $result->getStatus());
    $this->assertNotEmpty($result->getValidationErrors());
  }

  public function testStaleAssertionYieldsStaleStatus()
  {
    $srac = $this->parser->parseFile(__DIR__ . '/../fixtures/stale-srac.json');
    $spdx = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-spdx.json');

    $result = $this->correlator->correlateAssertion($srac, $spdx);

    $this->assertEquals(SracResult::STATUS_STALE, $result->getStatus());
  }

  public function testDraftUnreviewedAssertionPreserved()
  {
    $srac = $this->parser->parseFile(__DIR__ . '/../fixtures/unreviewed-srac.json');
    $spdx = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-spdx.json');

    $result = $this->correlator->correlateAssertion($srac, $spdx);

    $this->assertEquals(SracResult::STATUS_MATCHED, $result->getStatus());
    $safety = $result->getSafetyAssessment();
    $this->assertEquals('draft', $safety['assertionStatus']);
    $this->assertNull($safety['reviewer']);
  }

  public function testMissingSracDoesNotBecomeSafetyNegative()
  {
    $srac = $this->parser->parseFile(__DIR__ . '/../fixtures/unmatched-srac.json');
    $spdx = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-spdx.json');

    $result = $this->correlator->correlateAssertion($srac, $spdx);

    $this->assertEquals(SracResult::STATUS_UNMATCHED, $result->getStatus());
    // The asserted safety relevance is preserved as authored (ASIL-D), NOT converted to "not-safety-related"
    $safety = $result->getSafetyAssessment();
    $this->assertEquals('safety-related', $safety['safetyRelevance']);
    $this->assertEquals('ASIL-D', $safety['classification']);
  }

  public function testOriginalSbomAndSracRemainUnmutated()
  {
    $sracOriginal = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-srac.json');
    $spdxOriginal = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-spdx.json');

    $sracCopy = $sracOriginal;
    $spdxCopy = $spdxOriginal;

    $this->correlator->correlateAssertion($sracCopy, $spdxCopy);

    // INVARIANT: Neither SBOM nor SRAC input is mutated
    $this->assertEquals($sracOriginal, $sracCopy);
    $this->assertEquals($spdxOriginal, $spdxCopy);
  }
}
