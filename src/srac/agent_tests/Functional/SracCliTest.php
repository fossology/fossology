<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Srac\Test\Functional;

use PHPUnit\Framework\TestCase;

class SracCliTest extends TestCase
{
  private $scriptPath;
  private $fixturesDir;
  private $tempDir;

  protected function setUp(): void
  {
    $this->scriptPath = realpath(__DIR__ . '/../../agent/srac.php');
    $this->fixturesDir = realpath(__DIR__ . '/../fixtures');
    $this->tempDir = sys_get_temp_dir() . '/srac_test_' . uniqid();
    if (!is_dir($this->tempDir)) {
      mkdir($this->tempDir, 0777, true);
    }
  }

  protected function tearDown(): void
  {
    if (is_dir($this->tempDir)) {
      $files = glob($this->tempDir . '/*');
      foreach ($files as $file) {
        if (is_file($file)) {
          unlink($file);
        }
      }
      rmdir($this->tempDir);
    }
  }

  public function testCliHelpFlag()
  {
    $output = [];
    $exitCode = 0;
    exec("php " . escapeshellarg($this->scriptPath) . " --help", $output, $exitCode);

    $this->assertEquals(0, $exitCode);
    $this->assertStringContainsString('FOSSology SRAC Sidecar Correlation Agent', implode("\n", $output));
  }

  public function testCliValidSpdxDiscoveryAndCorrelation()
  {
    $sbom = $this->fixturesDir . '/valid-spdx.json';
    $outputFile = $this->tempDir . '/report.json';

    $cmd = sprintf(
      'php %s --sbom=%s --artifact-root=%s --output=%s',
      escapeshellarg($this->scriptPath),
      escapeshellarg($sbom),
      escapeshellarg($this->fixturesDir),
      escapeshellarg($outputFile)
    );

    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);

    $this->assertEquals(0, $exitCode, "Expected exit code 0 on match. Output: " . implode("\n", $output));
    $this->assertFileExists($outputFile);

    $report = json_decode(file_get_contents($outputFile), true);
    $this->assertEquals('matched', $report['matchStatus']);
    $this->assertEquals('0.2-draft', $report['schemaVersion']);
    $this->assertEquals('srac-synthetic-brake-monitor-001', $report['assertionId']);
    $this->assertEquals('SPDXRef-Package-brake-monitor', $report['matchedComponents'][0]['identifier']);
  }

  public function testCliValidCycloneDxDiscoveryAndCorrelation()
  {
    $sbom = $this->fixturesDir . '/valid-cyclonedx.json';
    $outputFile = $this->tempDir . '/report_cdx.json';

    $cmd = sprintf(
      'php %s --sbom=%s --artifact-root=%s --output=%s',
      escapeshellarg($this->scriptPath),
      escapeshellarg($sbom),
      escapeshellarg($this->fixturesDir),
      escapeshellarg($outputFile)
    );

    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);

    $this->assertEquals(0, $exitCode);
    $this->assertFileExists($outputFile);

    $report = json_decode(file_get_contents($outputFile), true);
    $this->assertEquals('matched', $report['matchStatus']);
    $this->assertEquals('CycloneDX', $report['sourceFormat']);
    $this->assertEquals('brake-monitor-ref', $report['matchedComponents'][0]['identifier']);
  }

  public function testCliExplicitSracOption()
  {
    $sbom = $this->fixturesDir . '/valid-spdx.json';
    $srac = $this->fixturesDir . '/unmatched-srac.json';
    $outputFile = $this->tempDir . '/report_unmatched.json';

    $cmd = sprintf(
      'php %s --sbom=%s --srac=%s --output=%s',
      escapeshellarg($this->scriptPath),
      escapeshellarg($sbom),
      escapeshellarg($srac),
      escapeshellarg($outputFile)
    );

    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);

    // Unmatched returns exit code 1
    $this->assertEquals(1, $exitCode);
    $this->assertFileExists($outputFile);

    $report = json_decode(file_get_contents($outputFile), true);
    $this->assertEquals('unmatched', $report['matchStatus']);
  }

  public function testCliTextFormat()
  {
    $sbom = $this->fixturesDir . '/valid-spdx.json';
    $srac = $this->fixturesDir . '/valid-srac.json';

    $cmd = sprintf(
      'php %s --sbom=%s --srac=%s --format=text',
      escapeshellarg($this->scriptPath),
      escapeshellarg($sbom),
      escapeshellarg($srac)
    );

    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);

    $this->assertEquals(0, $exitCode);
    $text = implode("\n", $output);
    $this->assertStringContainsString('FOSSOLOGY SRAC CORRELATION AUDIT REPORT', $text);
    $this->assertStringContainsString('Total Assertions: 1', $text);
  }

  public function testCliMissingSbomFails()
  {
    $output = [];
    $exitCode = 0;
    exec("php " . escapeshellarg($this->scriptPath), $output, $exitCode);

    $this->assertEquals(1, $exitCode);
    $this->assertStringContainsString('Usage:', implode("\n", $output));
  }
}
