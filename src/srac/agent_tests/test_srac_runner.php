<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Standalone test suite for SRAC parsing, validation, correlation, integrity, and reporting.
 *
 * Can be executed directly with `php test_srac_runner.php`.
 */

namespace Fossology\Srac\Test;

require_once __DIR__ . '/../agent/SracResult.php';
require_once __DIR__ . '/../agent/SracParser.php';
require_once __DIR__ . '/../agent/SracValidator.php';
require_once __DIR__ . '/../agent/SracIntegrityChecker.php';
require_once __DIR__ . '/../agent/SracCorrelator.php';
require_once __DIR__ . '/../agent/SracReportGenerator.php';
require_once __DIR__ . '/../agent/SracAgent.php';

use Fossology\Srac\SracParser;
use Fossology\Srac\SracValidator;
use Fossology\Srac\SracIntegrityChecker;
use Fossology\Srac\SracCorrelator;
use Fossology\Srac\SracReportGenerator;
use Fossology\Srac\SracResult;
use Fossology\Srac\SracAgent;

$passed = 0;
$failed = 0;

function assert_test($name, $condition, $detail = '')
{
  global $passed, $failed;
  if ($condition) {
    echo "  [PASS] $name\n";
    $passed++;
  } else {
    echo "  [FAIL] $name" . ($detail ? " -- $detail" : "") . "\n";
    $failed++;
  }
}

echo "\n============================================================\n";
echo "      FOSSOLOGY SRAC CORRELATION TEST SUITE RUNNER          \n";
echo "============================================================\n\n";

$parser = new SracParser();
$validator = new SracValidator();
$checker = new SracIntegrityChecker();
$correlator = new SracCorrelator($validator);
$generator = new SracReportGenerator();

$fixtures = __DIR__ . '/fixtures';

// --- Test 1: Parser valid and invalid inputs ---
echo "Section 1: SracParser\n";
$validSrac = $parser->parseFile("$fixtures/valid-srac.json");
assert_test("Parser parses valid JSON file", is_array($validSrac) && $validSrac['assertionId'] === 'srac-synthetic-brake-monitor-001');

try {
  $parser->parseString('{ malformed json }');
  assert_test("Parser rejects malformed JSON", false, "Did not throw exception");
} catch (\InvalidArgumentException $e) {
  assert_test("Parser rejects malformed JSON", strpos($e->getMessage(), 'Malformed JSON') !== false);
}

try {
  $parser->parseString('12345');
  assert_test("Parser rejects non-object root JSON", false, "Did not throw exception");
} catch (\InvalidArgumentException $e) {
  assert_test("Parser rejects non-object root JSON", strpos($e->getMessage(), 'Expected JSON object or array') !== false);
}

// --- Test 2: SracValidator schema & rules ---
echo "\nSection 2: SracValidator\n";
$valErrors = $validator->validateAssertion($validSrac);
assert_test("Valid assertion has 0 errors", count($valErrors) === 0, implode('; ', $valErrors));

$commSrac = $parser->parseFile("$fixtures/communication.srac.json");
$commErrors = $validator->validateAssertion($commSrac);
assert_test("Communication reference SRAC has 0 errors", count($commErrors) === 0, implode('; ', $commErrors));

$invalidSrac = $parser->parseFile("$fixtures/invalid-srac.json");
$invalidErrors = $validator->validateAssertion($invalidSrac);
assert_test("Invalid assertion produces validation errors", count($invalidErrors) > 0);
$errStr = implode('; ', $invalidErrors);
assert_test("Validator flags bad safetyRelevance.status", strpos($errStr, 'safetyRelevance.status') !== false);
assert_test("Validator flags duplicate element IDs", strpos($errStr, 'defined more than once') !== false);
assert_test("Validator flags unresolved internal references", strpos($errStr, 'does not resolve to an assertion element') !== false);
assert_test("Validator flags mismatched safetyIntegrityLevel", strpos($errStr, 'safetyIntegrityLevel') !== false);

// --- Test 3: SracIntegrityChecker ---
echo "\nSection 3: SracIntegrityChecker\n";
$hash = $checker->calculateStringSha256("test-content");
assert_test("Hash calculation matches native sha256", $hash === hash('sha256', "test-content"));
assert_test("Verify hash is case-insensitive", $checker->verifyHash(strtoupper($hash), strtolower($hash)));

$validSpdx = $parser->parseFile("$fixtures/valid-spdx.json");
$spdxRefs = $checker->discoverSracReferences($validSpdx);
assert_test("Discovers external reference in SPDX", count($spdxRefs) === 1 && $spdxRefs[0]['format'] === 'SPDX');
assert_test("SPDX reference has componentIdentifier", $spdxRefs[0]['componentIdentifier'] === 'SPDXRef-Package-brake-monitor');

$validCdx = $parser->parseFile("$fixtures/valid-cyclonedx.json");
$cdxRefs = $checker->discoverSracReferences($validCdx);
assert_test("Discovers external reference in CycloneDX", count($cdxRefs) === 1 && $cdxRefs[0]['format'] === 'CycloneDX');
assert_test("CycloneDX reference has componentIdentifier", $cdxRefs[0]['componentIdentifier'] === 'brake-monitor-ref');

$resolved = $checker->resolveAndVerifyArtifact($spdxRefs[0], $fixtures);
assert_test("Resolves and verifies artifact digest", file_exists($resolved));

try {
  $badRef = $spdxRefs[0];
  $badRef['sha256'] = str_repeat('0', 64);
  $checker->resolveAndVerifyArtifact($badRef, $fixtures);
  assert_test("Rejects artifact with digest mismatch", false);
} catch (\InvalidArgumentException $e) {
  assert_test("Rejects artifact with digest mismatch", strpos($e->getMessage(), 'mismatch') !== false);
}

// --- Test 4: SracCorrelator deterministic matching ---
echo "\nSection 4: SracCorrelator Deterministic Matching\n";
// 4.1 Valid SPDX exact match
$resSpdx = $correlator->correlateAssertion($validSrac, $validSpdx);
assert_test("Valid SPDX exact match status is 'matched'", $resSpdx->getStatus() === SracResult::STATUS_MATCHED);
assert_test("Correlation rule used is 'purl_version'", $resSpdx->getRule() === SracResult::RULE_PURL_VERSION);
assert_test("Matched component identifier matches SPDX", $resSpdx->getMatchedComponents()[0]['identifier'] === 'SPDXRef-Package-brake-monitor');

// 4.2 Valid CycloneDX exact match
$resCdx = $correlator->correlateAssertion($validSrac, $validCdx);
assert_test("Valid CycloneDX exact match status is 'matched'", $resCdx->getStatus() === SracResult::STATUS_MATCHED);
assert_test("Matched component identifier matches CycloneDX", $resCdx->getMatchedComponents()[0]['identifier'] === 'brake-monitor-ref');

// 4.3 Eclipse S-CORE prototype example
$commSpdx = $parser->parseFile("$fixtures/communication.spdx.json");
$commCdx = $parser->parseFile("$fixtures/communication.cdx.json");
$resCommSpdx = $correlator->correlateAssertion($commSrac, $commSpdx);
assert_test("Eclipse S-CORE communication SPDX matches", $resCommSpdx->getStatus() === SracResult::STATUS_MATCHED);
assert_test("Communication SPDX matched SPDXID", $resCommSpdx->getMatchedComponents()[0]['identifier'] === 'SPDXRef-score-communication-configuration');

$resCommCdx = $correlator->correlateAssertion($commSrac, $commCdx);
assert_test("Eclipse S-CORE communication CycloneDX matches", $resCommCdx->getStatus() === SracResult::STATUS_MATCHED);
assert_test("Communication CycloneDX matched bom-ref", $resCommCdx->getMatchedComponents()[0]['identifier'] === 'score-communication-configuration');

// 4.4 Artifact digest match
$digestSpdx = $validSpdx;
$digest = '313f1178628189d267c8cd0d75971bdac04c20ee';
$digestSpdx['packages'][0]['checksums'] = [['algorithm' => 'SHA-1', 'checksumValue' => $digest]];
$digestSrac = $validSrac;
$digestSrac['subject']['purl'] = 'pkg:generic/other@2.0.0';
$digestSrac['subject']['version'] = $digest;
$resDigest = $correlator->correlateAssertion($digestSrac, $digestSpdx);
assert_test("Artifact digest match works", $resDigest->getStatus() === SracResult::STATUS_MATCHED);
assert_test("Artifact digest rule recorded", $resDigest->getRule() === SracResult::RULE_ARTIFACT_DIGEST);

// 4.5 SBOM identifier match
$idSrac = $validSrac;
$idSrac['subject']['purl'] = 'pkg:generic/different@9.0.0';
$idSrac['subject']['version'] = '9.0.0';
$idSrac['subject']['componentPath'] = 'SPDXRef-Package-brake-monitor';
$resId = $correlator->correlateAssertion($idSrac, $validSpdx);
assert_test("SBOM identifier match works", $resId->getStatus() === SracResult::STATUS_MATCHED);
assert_test("SBOM identifier rule recorded", $resId->getRule() === SracResult::RULE_SBOM_IDENTIFIER);

// 4.6 Unmatched component
$unmatchedSrac = $parser->parseFile("$fixtures/unmatched-srac.json");
$resUnmatched = $correlator->correlateAssertion($unmatchedSrac, $validSpdx);
assert_test("Unmatched component status is 'unmatched'", $resUnmatched->getStatus() === SracResult::STATUS_UNMATCHED);
assert_test("Unmatched component has 0 matched components", count($resUnmatched->getMatchedComponents()) === 0);

// 4.7 Version mismatch
$verMismatchSrac = $validSrac;
$verMismatchSrac['subject']['version'] = '2.0.0';
$verMismatchSrac['subject']['purl'] = 'pkg:generic/synthetic-brake-monitor@2.0.0';
$resVerMismatch = $correlator->correlateAssertion($verMismatchSrac, $validSpdx);
assert_test("Version mismatch does not match", $resVerMismatch->getStatus() === SracResult::STATUS_UNMATCHED);

// 4.8 Name-only match prohibited
$nameOnlySrac = $validSrac;
$nameOnlySrac['subject']['name'] = 'synthetic-brake-monitor';
$nameOnlySrac['subject']['purl'] = 'pkg:generic/completely-different@3.0.0';
$nameOnlySrac['subject']['version'] = '3.0.0';
$nameOnlySrac['subject']['componentPath'] = 'other/path';
$resNameOnly = $correlator->correlateAssertion($nameOnlySrac, $validSpdx);
assert_test("Name-only match is PROHIBITED", $resNameOnly->getStatus() === SracResult::STATUS_UNMATCHED);

// 4.9 Ambiguous duplicate candidates
$ambiguousSpdx = $parser->parseFile("$fixtures/ambiguous-spdx.json");
$resAmbiguous = $correlator->correlateAssertion($validSrac, $ambiguousSpdx);
assert_test("Ambiguous duplicate candidates marked 'ambiguous'", $resAmbiguous->getStatus() === SracResult::STATUS_AMBIGUOUS);
assert_test("All ambiguous candidates are exposed", count($resAmbiguous->getMatchedComponents()) === 2);
assert_test("Candidate 1 is SPDXRef-Duplicate-1", $resAmbiguous->getMatchedComponents()[0]['identifier'] === 'SPDXRef-Duplicate-1');
assert_test("Candidate 2 is SPDXRef-Duplicate-2", $resAmbiguous->getMatchedComponents()[1]['identifier'] === 'SPDXRef-Duplicate-2');

// 4.10 Invalid SRAC status
$resInvalid = $correlator->correlateAssertion($invalidSrac, $validSpdx);
assert_test("Invalid assertion yields 'invalid' status", $resInvalid->getStatus() === SracResult::STATUS_INVALID);

// 4.11 Stale assertion status
$staleSrac = $parser->parseFile("$fixtures/stale-srac.json");
$resStale = $correlator->correlateAssertion($staleSrac, $validSpdx);
assert_test("Superseded assertion yields 'stale' status", $resStale->getStatus() === SracResult::STATUS_STALE);

// 4.12 Draft / unreviewed assertion
$unreviewedSrac = $parser->parseFile("$fixtures/unreviewed-srac.json");
$resUnreviewed = $correlator->correlateAssertion($unreviewedSrac, $validSpdx);
assert_test("Draft assertion matches and preserves review state", $resUnreviewed->getStatus() === SracResult::STATUS_MATCHED);
assert_test("Draft review status preserved", $resUnreviewed->getSafetyAssessment()['assertionStatus'] === 'draft');
assert_test("Reviewer is null", $resUnreviewed->getSafetyAssessment()['reviewer'] === null);

// 4.13 Missing SRAC does not become safety-negative
assert_test("Missing SRAC assertion preserves asserted ASIL-D", $resUnmatched->getSafetyAssessment()['classification'] === 'ASIL-D');
assert_test("Missing SRAC assertion preserves safety-related status", $resUnmatched->getSafetyAssessment()['safetyRelevance'] === 'safety-related');

// 4.14 Immutability of inputs
$sracBefore = $validSrac;
$spdxBefore = $validSpdx;
$correlator->correlateAssertion($validSrac, $validSpdx);
assert_test("SRAC document remains unchanged", $validSrac === $sracBefore);
assert_test("SBOM document remains unchanged", $validSpdx === $spdxBefore);

// --- Test 5: SracReportGenerator ---
echo "\nSection 5: SracReportGenerator\n";
$singleReport = $generator->generateSingleReport($resSpdx, 'SPDX', ['assertionSha256' => 'abc', 'sbomSha256' => 'def']);
assert_test("Single report has schemaVersion 0.2-draft", $singleReport['schemaVersion'] === '0.2-draft');
assert_test("Single report has matchStatus matched", $singleReport['matchStatus'] === 'matched');
assert_test("Single report has generator name fossology.srac", $singleReport['generator']['name'] === 'fossology.srac');

$compReport = $generator->generateComprehensiveReport([$resSpdx, $resUnmatched, $resInvalid, $resAmbiguous, $resStale], 'SPDX');
assert_test("Comprehensive report summary totalAssertions", $compReport['summary']['totalAssertions'] === 5);
assert_test("Summary matched count is 1", $compReport['summary']['matched'] === 1);
assert_test("Summary unmatched count is 1", $compReport['summary']['unmatched'] === 1);
assert_test("Summary invalid count is 1", $compReport['summary']['invalid'] === 1);
assert_test("Summary ambiguous count is 1", $compReport['summary']['ambiguous'] === 1);
assert_test("Summary stale count is 1", $compReport['summary']['stale'] === 1);

$textReport = $generator->generateTextReport($compReport);
assert_test("Text report contains audit header", strpos($textReport, 'FOSSOLOGY SRAC CORRELATION AUDIT REPORT') !== false);
assert_test("Text report contains Total Assertions: 5", strpos($textReport, 'Total Assertions: 5') !== false);

// --- Test 6: SracAgent runStandalone ---
echo "\nSection 6: SracAgent CLI Execution\n";
$agent = new SracAgent();
$tmpOut = tempnam(sys_get_temp_dir(), 'srac_out_');
list($exitCode, $content, $agentResult) = $agent->runStandalone([
  'sbom' => "$fixtures/valid-spdx.json",
  'artifact-root' => $fixtures,
  'output' => $tmpOut,
]);
assert_test("Agent runStandalone succeeds with exit code 0", $exitCode === 0);
assert_test("Agent writes report to output file", file_exists($tmpOut));
$writtenJson = json_decode(file_get_contents($tmpOut), true);
assert_test("Written report has matched status", $writtenJson['matchStatus'] === 'matched');
if (file_exists($tmpOut)) {
  unlink($tmpOut);
}

echo "\n============================================================\n";
echo "TEST RESULTS: Passed: $passed, Failed: $failed\n";
echo "============================================================\n";

exit($failed > 0 ? 1 : 0);
