<?php
/*
 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\CycloneDX;

class BomReportGeneratorTest extends \PHPUnit\Framework\TestCase
{
  public function testGenerateReportHasExpectedHeaderMetadata()
  {
    $generator = new BomReportGenerator();

    $bomdata = [
      'tool-version' => 'test',
      'maincomponent' => [
        'type' => 'application',
        'name' => 'test'
      ],
      'components' => []
    ];

    $report = $generator->generateReport($bomdata);

    $this->assertEquals('CycloneDX', $report['bomFormat']);
    $this->assertEquals('1.4', $report['specVersion']);
    $this->assertEquals(
      'https://cyclonedx.org/schema/bom-1.4.schema.json',
      $report['$schema']
    );
  }
}