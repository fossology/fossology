<?php
/*
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

namespace Fossology\Lib\Text;


class EncodingConverter implements Converter {
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

      if (!$detectedCharset)
      {
        $charsets = array('iso-8859-1', 'windows-1251', 'GB2312');
        foreach ($charsets as $charset)
        {
          $output = iconv($charset, self::UTF8_ENCODING . '//TRANSLIT', $input);
          if ($output) {
            return $output;
          }
        }
      } else
      {
        return iconv($detectedCharset, self::UTF8_ENCODING, $input);
      }
    }
  }

  public function isUtf8($input) {
      return mb_check_encoding($input, self::UTF8_ENCODING);
  }
}