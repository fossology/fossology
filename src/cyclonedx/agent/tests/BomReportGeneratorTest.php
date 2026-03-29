<?php
/*
 SPDX-FileCopyrightText: © 2026 Contributors to FOSSology

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\CycloneDX;

require_once(__DIR__ . "/../reportgenerator.php");

use PHPUnit\Framework\TestCase;

/**
 * @class BomReportGeneratorTest
 * @brief Tests for BomReportGenerator license text inclusion
 */
class BomReportGeneratorTest extends TestCase
{
  /** @var BomReportGenerator $generator */
  private $generator;

  protected function setUp(): void
  {
    $this->generator = new BomReportGenerator();
  }

  /**
   * @brief License text is included in output when textContent is provided
   */
  public function testCreateLicenseIncludesTextWhenProvided()
  {
    $licenseData = [
      'id'              => 'MIT',
      'url'             => 'https://opensource.org/licenses/MIT',
      'textContent'     => base64_encode('Permission is hereby granted...'),
      'textContentType' => 'text/plain',
    ];

    $result = $this->generator->createLicense($licenseData);

    $this->assertArrayHasKey('license', $result);
    $this->assertArrayHasKey('text', $result['license']);
    $this->assertEquals(base64_encode('Permission is hereby granted...'), $result['license']['text']['content']);
    $this->assertEquals('text/plain', $result['license']['text']['contentType']);
    $this->assertEquals('base64', $result['license']['text']['encoding']);
  }

  /**
   * @brief No text key is added when textContent is absent
   */
  public function testCreateLicenseOmitsTextWhenNotProvided()
  {
    $licenseData = [
      'id'  => 'Apache-2.0',
      'url' => 'https://www.apache.org/licenses/LICENSE-2.0',
    ];

    $result = $this->generator->createLicense($licenseData);

    $this->assertArrayHasKey('license', $result);
    $this->assertArrayNotHasKey('text', $result['license']);
  }

  /**
   * @brief No text key is added when textContent is empty string
   */
  public function testCreateLicenseOmitsTextWhenEmpty()
  {
    $licenseData = [
      'id'          => 'GPL-2.0-only',
      'textContent' => '',
    ];

    $result = $this->generator->createLicense($licenseData);

    $this->assertArrayHasKey('license', $result);
    $this->assertArrayNotHasKey('text', $result['license']);
  }

  /**
   * @brief LicenseRef expressions are returned as-is without a text block
   */
  public function testCreateLicenseReturnsExpressionForLicenseRef()
  {
    $licenseData = [
      'id'          => 'LicenseRef-custom-license',
      'textContent' => base64_encode('Custom license text'),
      'textContentType' => 'text/plain',
    ];

    $result = $this->generator->createLicense($licenseData);

    $this->assertArrayHasKey('expression', $result);
    $this->assertArrayNotHasKey('license', $result);
  }
}
