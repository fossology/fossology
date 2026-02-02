<?php
/*
 SPDX-FileCopyrightText: © 2025 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

class SpdxLicenseValidatorTest extends \PHPUnit\Framework\TestCase
{
  public function testIsValidLicenseRefWithValidIdentifiers()
  {
    $validIds = [
      'LicenseRef-fossology-MIT',
      'LicenseRef-fossology-GPL-2.0',
      'LicenseRef-fossology-Apache-2.0',
      'LicenseRef-fossology-BSD-3-Clause',
      'LicenseRef-test.license',
      'LicenseRef-123',
    ];

    foreach ($validIds as $id) {
      $this->assertTrue(
        SpdxLicenseValidator::isValidLicenseRef($id),
        "Expected '$id' to be valid"
      );
    }
  }

  public function testIsValidLicenseRefWithInvalidIdentifiers()
  {
    $invalidIds = [
      'LicenseRef-fossology-Public-Domain(c)',
      'LicenseRef-fossology-some_license',
      'LicenseRef-fossology-license@test',
      'LicenseRef-fossology-license#1',
      'LicenseRef-fossology-license with spaces',
      'LicenseRef-fossology-license(test)',
      '',
      'NotLicenseRef-test',
    ];

    foreach ($invalidIds as $id) {
      $this->assertFalse(
        SpdxLicenseValidator::isValidLicenseRef($id),
        "Expected '$id' to be invalid"
      );
    }
  }

  public function testIsLicenseRef()
  {
    $this->assertTrue(SpdxLicenseValidator::isLicenseRef('LicenseRef-test'));
    $this->assertTrue(SpdxLicenseValidator::isLicenseRef('LicenseRef-fossology-MIT'));
    $this->assertFalse(SpdxLicenseValidator::isLicenseRef('MIT'));
    $this->assertFalse(SpdxLicenseValidator::isLicenseRef(''));
  }

  public function testExtractIdstring()
  {
    $this->assertEquals('test', SpdxLicenseValidator::extractIdstring('LicenseRef-test'));
    $this->assertEquals('fossology-MIT', SpdxLicenseValidator::extractIdstring('LicenseRef-fossology-MIT'));
    $this->assertEquals('', SpdxLicenseValidator::extractIdstring('MIT'));
    $this->assertEquals('', SpdxLicenseValidator::extractIdstring(''));
  }

  public function testGetValidationErrors()
  {
    $errors = SpdxLicenseValidator::getValidationErrors('LicenseRef-fossology-Public-Domain(c)');
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('invalid', $errors[0]);

    $errors = SpdxLicenseValidator::getValidationErrors('LicenseRef-fossology-some_license');
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('_', $errors[0]);

    $errors = SpdxLicenseValidator::getValidationErrors('LicenseRef-fossology-MIT');
    $this->assertEmpty($errors);

    $errors = SpdxLicenseValidator::getValidationErrors('');
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('empty', $errors[0]);
  }

  public function testSanitizeLicenseRef()
  {
    $this->assertEquals(
      'LicenseRef-fossology-Public-Domain-c',
      SpdxLicenseValidator::sanitizeLicenseRef('LicenseRef-fossology-Public-Domain(c)')
    );

    $this->assertEquals(
      'LicenseRef-fossology-some-license',
      SpdxLicenseValidator::sanitizeLicenseRef('LicenseRef-fossology-some_license')
    );

    $this->assertEquals(
      'LicenseRef-fossology-license-test',
      SpdxLicenseValidator::sanitizeLicenseRef('LicenseRef-fossology-license@test')
    );

    $this->assertEquals(
      'LicenseRef-fossology-MIT',
      SpdxLicenseValidator::sanitizeLicenseRef('LicenseRef-fossology-MIT')
    );

    $this->assertEquals(
      'MIT',
      SpdxLicenseValidator::sanitizeLicenseRef('MIT')
    );
  }

  public function testGetSuggestion()
  {
    $this->assertEquals(
      'LicenseRef-fossology-Public-Domain-c',
      SpdxLicenseValidator::getSuggestion('LicenseRef-fossology-Public-Domain(c)')
    );

    $this->assertEquals(
      'LicenseRef-fossology-some-license',
      SpdxLicenseValidator::getSuggestion('LicenseRef-fossology-some_license')
    );
  }

  public function testSanitizeWithMultipleInvalidChars()
  {
    $result = SpdxLicenseValidator::sanitizeLicenseRef('LicenseRef-fossology-license(test)_with@special#chars');
    $this->assertTrue(SpdxLicenseValidator::isValidLicenseRef($result));
    $this->assertStringStartsWith('LicenseRef-', $result);
  }

  public function testSanitizePreservesValidChars()
  {
    $input = 'LicenseRef-fossology-Apache-2.0';
    $result = SpdxLicenseValidator::sanitizeLicenseRef($input);
    $this->assertEquals($input, $result);
  }
}

