<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: D.Fognini, S. Weber, J.Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Common function to handle strings
 */

// For compatibility with older php versions
if (! defined('ENT_SUBSTITUTE')) {
  define('ENT_SUBSTITUTE', 0);
}

/**
 * Convert string to UTF-8
 * @param string $content String to be converted
 * @param bool   $toHTML  True to return HTML compatible strings
 * @return string UTF8 converted string
 */
function convertToUTF8($content, $toHTML=true)
{
  if (strlen($content) == 0) {
    return '';
  }
  if (checkUTF8($content)) {
    $output1 = $content;
  } else {
    $output1 = tryConvertToUTF8($content);
    if (! $output1 || ! checkUTF8($output1)) {
      $output1 = $toHTML ? "<Unknown encoding>" : "<b>Unknown encoding</b>";
    }
  }

  if (! $toHTML) {
    return $output1;
  }
  return (htmlspecialchars($output1, ENT_SUBSTITUTE, "UTF-8")) ?: "<b>Unknown encoding</b>";
}

/**
 * Check if the given string is already UTF-8 encoded
 * @param string $content String to check
 * @return boolean True if encoded in UTF-8, false otherwise
 */
function checkUTF8($content)
{
  return mb_check_encoding($content, "UTF-8");
}

/**
 * Try to convert a string to UTF-8 encoded string
 * @param string $content String to be converted
 * @return boolean|string UTF-8 converted string, false if cannot be converted
 */
function tryConvertToUTF8($content)
{
  $inCharset = mb_detect_encoding($content, mb_detect_order(), true);
  $output1 = false;
  if (! $inCharset) {
    $charsets = array('iso-8859-1', 'windows-1251', 'GB2312');
    foreach ($charsets as $charset) {
      $output1 = iconv($charset, "UTF-8", $content);
      if ($output1) {
        break;
      }
    }
  } else if ($inCharset != "UTF-8") {
    $output1 = iconv($inCharset, "UTF-8", $content);
  }
  return $output1;
}
