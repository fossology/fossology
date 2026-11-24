<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

/**
 * @file
 * @brief Newline escaping shared by BulkTextExport, CustomTextExport and CustomTextImport
 */

/**
 * @class CustomTextEscaping
 * @brief Escape/unescape newlines for single-line CSV fields
 */
class CustomTextEscaping
{
  /**
   * @brief Flatten real newlines to a literal \n so a field stays on one
   * physical CSV line. Backslashes are escaped first so unescapeNewlines()
   * can reverse the transform exactly, even if the text itself already
   * contains a literal backslash-n sequence.
   * @param string|null $value
   * @return string
   */
  public static function escapeNewlines($value)
  {
    if ($value === null) {
      return '';
    }
    $value = str_replace('\\', '\\\\', (string) $value);
    return str_replace(array("\r\n", "\r", "\n"), '\\n', $value);
  }

  /**
   * @brief Reverse escapeNewlines(): literal \n becomes a real newline,
   * any other escaped character is unescaped to itself.
   * @param string|null $value
   * @return string
   */
  public static function unescapeNewlines($value)
  {
    if ($value === null) {
      return '';
    }
    return preg_replace_callback('/\\\\(.)/', function ($m) {
      return $m[1] === 'n' ? "\n" : $m[1];
    }, (string) $value);
  }
}
