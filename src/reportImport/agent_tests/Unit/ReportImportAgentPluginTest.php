<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Test/Reflectory.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Plugin/FO_Plugin.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-plugin.php');
require_once(__DIR__ . '/../../ui/ReportImportAgentPlugin.php');

/**
 * @class ReportImportAgentPluginTest
 * @brief Tests for ReportImportAgentPlugin::sanitizeReportFilename()
 */
class ReportImportAgentPluginTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @return array[] Pairs of client supplied name and expected stored name
   */
  public static function filenameProvider(): array
  {
    return [
      'plain name is untouched'      => ['report.rdf.xml', 'report.rdf.xml'],
      'suffix of json is kept'       => ['bom.cdx.json', 'bom.cdx.json'],
      'space is replaced'            => ['my report.xml', 'my_report.xml'],
      'unix directory is stripped'   => ['sub/dir/report.xml', 'report.xml'],
      'windows directory is stripped' => ['C:\\tmp\\report.xml', 'report.xml'],
      'traversal is stripped'        => ['../../evil.xml', 'evil.xml'],
      'single quote is replaced'     => ["it's.xml", 'it_s.xml'],
      'double quote is replaced'     => ['say"hi".xml', 'say_hi_.xml'],
      'leading dots are removed'     => ['..hidden.xml', 'hidden.xml'],
      'only dots falls back'         => ['..', 'report'],
      'empty falls back'             => ['', 'report'],
    ];
  }

  /**
   * @test
   * -# Sanitize a client supplied file name
   * -# Verify directories, quotes and whitespace are gone and the suffix stays
   * @dataProvider filenameProvider
   */
  public function testSanitizeReportFilename(string $input, string $expected): void
  {
    $this->assertSame($expected,
      ReportImportAgentPlugin::sanitizeReportFilename($input));
  }

  /**
   * @test
   * -# Sanitize a very long file name
   * -# Verify the result is shortened but the suffix is preserved
   */
  public function testSanitizeReportFilenameKeepsSuffixOfLongNames(): void
  {
    $result = ReportImportAgentPlugin::sanitizeReportFilename(
      str_repeat('a', 300) . '.rdf.xml');
    $this->assertLessThanOrEqual(100, strlen($result));
    $this->assertStringEndsWith('.rdf.xml', $result);
  }

  /**
   * @test
   * -# Sanitize a name made of characters that are unsafe in the jq_args
   * -# Verify the result only has characters that need no shell quoting
   */
  public function testSanitizeReportFilenameOutputIsShellSafe(): void
  {
    $result = ReportImportAgentPlugin::sanitizeReportFilename("a b'c\"d\$e;f\u{e9}.xml");
    $this->assertMatchesRegularExpression('/^[A-Za-z0-9._-]+$/', $result);
  }
}
