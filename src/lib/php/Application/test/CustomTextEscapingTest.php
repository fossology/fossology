<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

/**
 * @class CustomTextEscapingTest
 * @brief Test for class CustomTextEscaping
 */
class CustomTextEscapingTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @test
   * @dataProvider roundTripProvider
   */
  public function testEscapeThenUnescapeRoundTrips(string $original, string $expectedAfterCrlfNormalization): void
  {
    $escaped = CustomTextEscaping::escapeNewlines($original);
    $restored = CustomTextEscaping::unescapeNewlines($escaped);

    $this->assertSame($expectedAfterCrlfNormalization, $restored);
  }

  public static function roundTripProvider(): array
  {
    return [
      'literal backslash-n, no real newline' => [
        'printf("hello\\n");', 'printf("hello\\n");'
      ],
      'real newlines' => [
        "line one\nline two\nline three", "line one\nline two\nline three"
      ],
      'backslash immediately before a real newline' => [
        "a\\backslash then\nnewline", "a\\backslash then\nnewline"
      ],
      'trailing lone backslash' => [
        "trailing backslash\\", "trailing backslash\\"
      ],
      'windows CRLF normalizes to LF' => [
        "windows\r\nline", "windows\nline"
      ],
      'empty string' => [
        '', ''
      ],
    ];
  }

  /**
   * @test
   * -# Escape a value containing a real newline.
   * -# Verify the result has no real newline characters, only the literal '\n'.
   */
  public function testEscapeFlattensToSingleLine(): void
  {
    $escaped = CustomTextEscaping::escapeNewlines("multi\nline\nvalue");

    $this->assertStringNotContainsString("\n", $escaped);
    $this->assertSame('multi\\nline\\nvalue', $escaped);
  }

  /**
   * @test
   * -# Call escapeNewlines/unescapeNewlines with null.
   * -# Verify both return an empty string rather than raising a warning.
   */
  public function testNullIsTreatedAsEmptyString(): void
  {
    $this->assertSame('', CustomTextEscaping::escapeNewlines(null));
    $this->assertSame('', CustomTextEscaping::unescapeNewlines(null));
  }
}
