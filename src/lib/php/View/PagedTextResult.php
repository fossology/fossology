<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\View;


// For compatibility with older php versions
if (!defined('ENT_SUBSTITUTE')) {
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

    if (self::TARGET_CHARSET == "UTF-8") {
      return convertToUTF8($text, true);
    }

    return htmlspecialchars(mb_convert_encoding($text, self::TARGET_CHARSET), ENT_SUBSTITUTE, self::TARGET_CHARSET);
  }
}
