<?php
/*
Copyright (C) 2014, Siemens AG
Author: Andreas Würl

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

namespace Fossology\Lib\View;


// For compatibility with older php versions
if (!defined('ENT_SUBSTITUTE'))
{
  define('ENT_SUBSTITUTE', 0); //This might give an empty string, but with the conversion to UTF-8 we might not run into this
}


class PagedTextResult extends PagedResult
{
  const TARGET_CHARSET = "UTF-8";

  /**
   * @param string $text
   * @return string
   */
  protected function renderContentText($text)
  {

    if(self::TARGET_CHARSET == "UTF-8"){
      return convertToUTF8($text, true);
    }

    return htmlspecialchars(mb_convert_encoding($text, self::TARGET_CHARSET), ENT_SUBSTITUTE, self::TARGET_CHARSET);
  }
}