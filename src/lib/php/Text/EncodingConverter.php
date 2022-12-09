<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Text;


class EncodingConverter implements Converter
{
  const UTF8_ENCODING = "UTF-8";

  /**
   * @param string $input
   * @return string
   */
  function convert($input)
  {
    if ($this->isUtf8($input)) {
      return $input;
    } else {
      $encodings = array("ASCII", "UTF-8", "Windows-1252", "ISO-8859-15", "ISO-8859-1", "GB2312");
      $detectedCharset = mb_detect_encoding($input, $encodings, true);

      if (!$detectedCharset) {
        $charsets = array('iso-8859-1', 'windows-1251', 'GB2312');
        foreach ($charsets as $charset) {
          $output = iconv($charset, self::UTF8_ENCODING . '//TRANSLIT', $input);
          if ($output) {
            return $output;
          }
        }
      } else {
        return iconv($detectedCharset, self::UTF8_ENCODING, $input);
      }
    }
  }

  public function isUtf8($input)
  {
      return mb_check_encoding($input, self::UTF8_ENCODING);
  }
}
