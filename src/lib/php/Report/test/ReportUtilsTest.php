<?php
/*
 SPDX-FileCopyrightText: © 2026 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use PHPUnit\Framework\TestCase;

/**
 * @class ReportUtilsTest
 * @brief Tests for the report naming helpers
 */
class ReportUtilsTest extends TestCase
{
  protected function setUp(): void
  {
    $GLOBALS['SysConf']['FOSSOLOGY']['path'] = '/srv/repo';
  }

  /**
   * @brief Names the report agents produced before they shared this helper
   * @return array
   */
  public function reportNameProvider()
  {
    return [
      ['spdx2', 'SPDX2_curl.tar.gz.spdx.rdf'],
      ['spdx2tv', 'SPDX2TV_curl.tar.gz.spdx'],
      ['spdx2csv', 'SPDX2CSV_curl.tar.gz.csv'],
      ['dep5', 'DEP5_curl.tar.gz.txt'],
      ['spdx3json', 'SPDX3JSON_curl.tar.gz.json'],
      ['spdx3jsonld', 'SPDX3JSONLD_curl.tar.gz.jsonld'],
      ['spdx3rdf', 'SPDX3RDF_curl.tar.gz.spdx.rdf'],
      ['spdx3tv', 'SPDX3TV_curl.tar.gz.spdx'],
      ['cyclonedx_json', 'CYCLONEDX_JSON_curl.tar.gz.json'],
      ['clixml', 'CLIXML_curl.tar.gz.xml'],
      ['xml', 'XML_curl.tar.gz.xml'],
      ['readmeoss', 'ReadMe_OSS_curl.tar.gz.txt'],
    ];
  }

  /**
   * @brief Formats an agent can hold, from their OUTPUT_FORMAT constants
   * @return array
   */
  public function agentFormatProvider()
  {
    return [
      // SpdxAgent::AVAILABLE_OUTPUT_FORMATS
      ['spdx2'], ['spdx2tv'], ['dep5'], ['spdx2csv'],
      ['spdx3jsonld'], ['spdx3json'], ['spdx3rdf'], ['spdx3tv'],
      // CycloneDXAgent::DEFAULT_OUTPUT_FORMAT
      ['cyclonedx_json'],
      // CliXmlAgent::DEFAULT_OUTPUT_FORMAT and ::AVAILABLE_OUTPUT_FORMATS
      ['clixml'], ['xml'],
      // ReadmeOSS writes one format only
      ['readmeoss'],
    ];
  }

  /**
   * @test
   * @dataProvider reportNameProvider
   * -# Build the base name of every supported format
   * -# Check it matches the name the agent used to build inline
   */
  public function testCanonicalReportBasename($format, $expected)
  {
    $this->assertEquals($expected,
      ReportUtils::canonicalReportBasename($format, 'curl.tar.gz'));
  }

  /**
   * @test
   * @dataProvider agentFormatProvider
   * -# Take every output format an agent can be running with
   * -# Check the report it writes gets an extension
   */
  public function testEveryAgentFormatHasAnExtension($format)
  {
    $this->assertNotEmpty(ReportUtils::reportFileExtension($format),
      "no extension known for format $format");
  }

  /**
   * @test
   * -# The merge calls the CycloneDX format cyclonedx, the agent calls the
   *    same format cyclonedx_json
   * -# Check both name the same file, so a merge finds what the agent wrote
   */
  public function testCycloneDxFormatAliasNamesTheSameReport()
  {
    $this->assertEquals(
      ReportUtils::canonicalReportBasename('cyclonedx_json', 'curl.tar.gz'),
      ReportUtils::canonicalReportBasename('cyclonedx', 'curl.tar.gz'));
    $this->assertEquals('.json',
      ReportUtils::extensionForAggregatorFormat('cyclonedx'));
  }

  /**
   * @test
   * -# Build a report path
   * -# Check it is the base name under the repository report directory
   */
  public function testCanonicalReportPath()
  {
    $this->assertEquals('/srv/repo/report/SPDX2TV_curl.tar.gz.spdx',
      ReportUtils::canonicalReportPath('spdx2tv', 'curl.tar.gz'));
  }

  /**
   * @test
   * -# Build a report path with a trailing slash in the repository path
   * -# Check the path does not get a doubled separator
   */
  public function testCanonicalReportPathWithTrailingSlash()
  {
    $GLOBALS['SysConf']['FOSSOLOGY']['path'] = '/srv/repo/';
    $this->assertEquals('/srv/repo/report/CLIXML_curl.tar.gz.xml',
      ReportUtils::canonicalReportPath('clixml', 'curl.tar.gz'));
  }

  /**
   * @test
   * -# Ask for the extension of every merge format
   * -# Check it matches the extension of the canonical name
   */
  public function testExtensionForAggregatorFormatMatchesName()
  {
    foreach (ReportUtils::AGGREGATOR_FORMATS as $format) {
      $extension = ReportUtils::extensionForAggregatorFormat($format);
      $this->assertNotEmpty($extension, "no extension for $format");
      $this->assertStringEndsWith($extension,
        ReportUtils::canonicalReportBasename($format, 'curl.tar.gz'));
    }
  }

  /**
   * @test
   * -# Check a merged report path is recognised as aggregated
   * -# Check a single upload report path is not
   */
  public function testIsAggregatedReportPath()
  {
    $this->assertTrue(ReportUtils::isAggregatedReportPath(
      '/srv/repo/report/aggregated_spdx2tv_42.spdx'));
    $this->assertFalse(ReportUtils::isAggregatedReportPath(
      '/srv/repo/report/SPDX2TV_curl.tar.gz.spdx'));
    $this->assertFalse(ReportUtils::isAggregatedReportPath(
      '/srv/repo/aggregated_reports/SPDX2TV_curl.tar.gz.spdx'));
  }

  /**
   * @test
   * -# Check the provenance sidecar of a merged report is recognised
   * -# Check the merged report itself is not
   */
  public function testIsProvenanceReportPath()
  {
    $this->assertTrue(ReportUtils::isProvenanceReportPath(
      '/srv/repo/report/aggregated_spdx2tv_42.provenance.json'));
    $this->assertFalse(ReportUtils::isProvenanceReportPath(
      '/srv/repo/report/aggregated_spdx2tv_42.spdx'));
  }
}
