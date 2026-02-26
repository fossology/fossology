/*
 * SPDX-FileCopyrightText: © 2026 Abdullah Shahid
 * SPDX-License-Identifier: GPL-2.0-only
 */

namespace Fossology\CycloneDX;

require_once __DIR__ . '/../../agent/reportgenerator.php';

class BomReportGeneratorTest extends \PHPUnit\Framework\TestCase
{
  public function testGenerateReportHasExpectedHeaderMetadata()
  {
    $gen = new BomReportGenerator();

    $bomdata = [
      'tool-version' => 'test',
      'maincomponent' => ['type' => 'application', 'name' => 'main'],
      'components' => [],
    ];

    $report = $gen->generateReport($bomdata);

    $this->assertSame('CycloneDX', $report['bomFormat']);
    $this->assertSame('1.4', $report['specVersion']);
    $this->assertSame('https://cyclonedx.org/schema/bom-1.4.schema.json', $report['$schema']);
    $this->assertArrayHasKey('serialNumber', $report);
    $this->assertArrayHasKey('metadata', $report);
    $this->assertArrayHasKey('timestamp', $report['metadata']);
  }
}
