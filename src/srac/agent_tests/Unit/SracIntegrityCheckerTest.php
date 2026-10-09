<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Srac\Test\Unit;

use Fossology\Srac\SracIntegrityChecker;
use Fossology\Srac\SracParser;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../agent/SracParser.php';
require_once __DIR__ . '/../../agent/SracIntegrityChecker.php';

class SracIntegrityCheckerTest extends TestCase
{
  /** @var SracIntegrityChecker */
  private $checker;

  /** @var SracParser */
  private $parser;

  protected function setUp(): void
  {
    $this->checker = new SracIntegrityChecker();
    $this->parser = new SracParser();
  }

  public function testCalculateSha256()
  {
    $hash = $this->checker->calculateStringSha256("test-content");
    $this->assertEquals(hash('sha256', "test-content"), $hash);
  }

  public function testVerifyHashCaseInsensitive()
  {
    $hash1 = "4B52A2EC6712866FA5DE7DBFD14E780C0D1DF71BDFCDbb24f0ea3aec48d88d2c";
    $hash2 = "4b52a2ec6712866fa5de7dbfd14e780c0d1df71bdfcdbb24f0ea3aec48d88d2c";
    $this->assertTrue($this->checker->verifyHash($hash1, $hash2));
  }

  public function testDiscoverSpdxReferences()
  {
    $sbom = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-spdx.json');
    $refs = $this->checker->discoverSracReferences($sbom);
    $this->assertCount(1, $refs);
    $this->assertEquals('SPDX', $refs[0]['format']);
    $this->assertEquals('SPDXRef-Package-brake-monitor', $refs[0]['componentIdentifier']);
    $this->assertEquals('4b52a2ec6712866fa5de7dbfd14e780c0d1df71bdfcdbb24f0ea3aec48d88d2c', $refs[0]['sha256']);
  }

  public function testDiscoverCycloneDxReferences()
  {
    $sbom = $this->parser->parseFile(__DIR__ . '/../fixtures/valid-cyclonedx.json');
    $refs = $this->checker->discoverSracReferences($sbom);
    $this->assertCount(1, $refs);
    $this->assertEquals('CycloneDX', $refs[0]['format']);
    $this->assertEquals('brake-monitor-ref', $refs[0]['componentIdentifier']);
    $this->assertEquals('4b52a2ec6712866fa5de7dbfd14e780c0d1df71bdfcdbb24f0ea3aec48d88d2c', $refs[0]['sha256']);
  }

  public function testDiscoverCommunicationSpdxReferences()
  {
    $sbom = $this->parser->parseFile(__DIR__ . '/../fixtures/communication.spdx.json');
    $refs = $this->checker->discoverSracReferences($sbom);
    $this->assertCount(1, $refs);
    $this->assertEquals('2108209f8a367eb370de5082b034ac015d6c9df9606c7a065c974d5c08344eea', $refs[0]['sha256']);
  }

  public function testResolveAndVerifyArtifactSuccess()
  {
    $ref = [
      'uri' => 'https://example.org/srac/valid-srac.json',
      'sha256' => '4b52a2ec6712866fa5de7dbfd14e780c0d1df71bdfcdbb24f0ea3aec48d88d2c'
    ];
    $resolved = $this->checker->resolveAndVerifyArtifact($ref, __DIR__ . '/../fixtures');
    $this->assertFileExists($resolved);
  }

  public function testResolveAndVerifyArtifactDigestMismatchThrows()
  {
    $ref = [
      'uri' => 'https://example.org/srac/valid-srac.json',
      'sha256' => str_repeat('0', 64)
    ];
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('SRAC artifact SHA-256 mismatch');
    $this->checker->resolveAndVerifyArtifact($ref, __DIR__ . '/../fixtures');
  }

  public function testResolveNonExistentArtifactThrows()
  {
    $ref = [
      'uri' => 'https://example.org/srac/does-not-exist.json',
      'sha256' => str_repeat('a', 64)
    ];
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Referenced SRAC artifact is unavailable');
    $this->checker->resolveAndVerifyArtifact($ref, __DIR__ . '/../fixtures');
  }
}
