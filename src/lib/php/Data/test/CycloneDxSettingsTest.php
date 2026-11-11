<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG
 Author: Shaheem Azmal M MD <shaheem.azmal@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

use Fossology\Lib\Data\Report\CycloneDxSettings;

class CycloneDxSettingsTest extends \PHPUnit\Framework\TestCase
{
  public function testDefaults()
  {
    $settings = new CycloneDxSettings();
    $this->assertFalse($settings->isLicenseCommentEnabled());
    $this->assertFalse($settings->isIgnoreFilesWithoutInfo());
    $this->assertFalse($settings->isOsselotExport());
    $this->assertSame('fossology:', $settings->getTagNamespace());
    $this->assertSame('1.7', $settings->getSpecVersion());
  }

  public static function provideNamespaces()
  {
    return [
      'default' => ['fossology:', 'fossology:'],
      'colon appended' => ['acme', 'acme:'],
      'nested' => ['acme:product:', 'acme:product:'],
      'trimmed' => ['  acme:  ', 'acme:'],
      'comma' => ['acme,inc:', null],
      'space' => ['acme inc:', null],
      'html' => ['<b>:', null],
      'empty segment' => ['acme::', null],
      'leading colon' => [':acme:', null],
      'empty' => ['', null],
      'too long' => [str_repeat('a', CycloneDxSettings::MAX_NAMESPACE_LENGTH) . ':', null],
      'longest' => [str_repeat('a', CycloneDxSettings::MAX_NAMESPACE_LENGTH - 1), str_repeat('a', CycloneDxSettings::MAX_NAMESPACE_LENGTH - 1) . ':'],
    ];
  }

  /**
   * @dataProvider provideNamespaces
   */
  public function testNormaliseNamespace($input, $expected)
  {
    $this->assertSame($expected, CycloneDxSettings::normaliseNamespace($input));
  }

  public function testNormaliseSpecVersion()
  {
    foreach (CycloneDxSettings::SUPPORTED_VERSIONS as $version) {
      $this->assertSame($version, CycloneDxSettings::normaliseSpecVersion($version));
    }
    $this->assertNull(CycloneDxSettings::normaliseSpecVersion('1.3'));
    $this->assertNull(CycloneDxSettings::normaliseSpecVersion('unchecked'));
    $this->assertNull(CycloneDxSettings::normaliseSpecVersion(null));
  }

  public function testFromUserSettingsLegacyThreeFields()
  {
    $settings = CycloneDxSettings::fromUserSettings('checked,unchecked,checked');
    $this->assertTrue($settings->isOsselotExport());
    $this->assertFalse($settings->isLicenseCommentChecked());
    $this->assertTrue($settings->isIgnoreFilesWithoutInfo());
    $this->assertSame('fossology:', $settings->getTagNamespace());
    $this->assertSame('1.7', $settings->getSpecVersion());
  }

  public function testFromUserSettingsNullAndGarbage()
  {
    $this->assertEquals(new CycloneDxSettings(), CycloneDxSettings::fromUserSettings(null));
    $settings = CycloneDxSettings::fromUserSettings('x,y,z,acme inc,9.9');
    $this->assertEquals(new CycloneDxSettings(), $settings);
  }

  public function testUserSettingsRoundTrip()
  {
    $settings = new CycloneDxSettings(true, false, true, 'acme:', '1.5');
    $this->assertSame('checked,checked,unchecked,acme:,1.5', $settings->toUserSettings());
    $this->assertEquals($settings, CycloneDxSettings::fromUserSettings($settings->toUserSettings()));
  }

  public function testReportInfoRoundTrip()
  {
    $settings = new CycloneDxSettings(false, true, false, 'acme:x:', '1.4');
    $this->assertEquals($settings, CycloneDxSettings::fromReportInfo($settings->toReportInfo()));
  }

  public function testFromReportInfoUnusable()
  {
    $this->assertNull(CycloneDxSettings::fromReportInfo(null));
    $this->assertNull(CycloneDxSettings::fromReportInfo(''));
    $this->assertNull(CycloneDxSettings::fromReportInfo('unchecked,unchecked,unchecked'));
  }

  public function testFromReportInfoInvalidValuesFallBack()
  {
    $settings = CycloneDxSettings::fromReportInfo('{"tagNamespace":"a,b","specVersion":"2.0","osselotExport":true}');
    $this->assertSame('fossology:', $settings->getTagNamespace());
    $this->assertSame('1.7', $settings->getSpecVersion());
    $this->assertTrue($settings->isOsselotExport());
  }

  public function testOsselotImpliesLicenseComment()
  {
    $settings = new CycloneDxSettings(false, false, true);
    $this->assertTrue($settings->isLicenseCommentEnabled());
    $this->assertFalse($settings->isLicenseCommentChecked());
  }
}
