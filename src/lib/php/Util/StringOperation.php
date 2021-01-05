<?php
/*
Copyright (C) 2015, Siemens AG

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
    $headLength = 0;
    $maxNumberOfCharsToCompare = min(strlen($a), strlen($b));
    while ($headLength < $maxNumberOfCharsToCompare &&
      $a{$headLength} === $b{$headLength}) {
      $headLength += 1;
    }
    return substr($a,0,$headLength);
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
}
