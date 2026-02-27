<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/


namespace Fossology\Lib\Util;

class StringOperation
{
  /**
   * @param string
   * @param string
   * @return string
   */
  public static function getCommonHead($a, $b)
  {

    if (function_exists('mb_strlen')) {
      $encoding = 'UTF-8';
      $headLength = 0;

      $maxNumberOfCharsToCompare = min(
        mb_strlen($a, $encoding),
        mb_strlen($b, $encoding)
      );

      while ($headLength < $maxNumberOfCharsToCompare &&
        mb_substr($a, $headLength, 1, $encoding) === mb_substr($b, $headLength, 1, $encoding)
      ) {
        $headLength++;
      }

      return mb_substr($a, 0, $headLength, $encoding);
    }

    $headLength = 0;
    $maxNumberOfCharsToCompare = min(strlen($a), strlen($b));

    while (
      $headLength < $maxNumberOfCharsToCompare &&
      $a[$headLength] === $b[$headLength]
    ) {
      $headLength++;
    }

    return substr($a, 0, $headLength);
  }
  /**
   * Replace any non-printable characters with a given character
   * @param string $input   String to clean
   * @param string $replace Replace control char with this
   * @return string Input string with non-printable character removed
   */
  public static function replaceUnicodeControlChar($input, $replace="")
  {
    // 'Non-printable' is ASCII < 0x20 (excluding \r, \n and tab) and
    // 0x7F - 0x9F.
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', $replace,
      $input);
  }

  /**
   * Polyfill for PHP8's str_starts_with
   * https://www.php.net/manual/en/function.str-starts-with.php
   * @param string $haystack String to search in
   * @param string $needle   String to search for
   * @return bool True if haystack starts with needle.
   */
  public static function stringStartsWith($haystack, $needle)
  {
    return strncmp($haystack, $needle, strlen($needle)) === 0;
  }

  /**
   * Polyfill for PHP8's str_ends_with
   * https://www.php.net/manual/en/function.str-ends-with.php
   * @param string $haystack String to search in
   * @param string $needle   String to search for
   * @return bool True if haystack ends with needle.
   */
  public static function stringEndsWith($haystack, $needle)
  {
    return substr_compare($haystack, $needle, -strlen($needle)) === 0;
  }
}
