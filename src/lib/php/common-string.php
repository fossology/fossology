<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
 * Author: D.Fognini, S. Weber, J.Najjar
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

// For compatibility with older php versions
if (!defined('ENT_SUBSTITUTE'))
{
  define('ENT_SUBSTITUTE', 0); //This might give an empty string, but with the conversion to UTF-8 we might not run into this
}

/**
 * @param $content
 * @return string
 */
function convertToUTF8($content, $toHTML=true)
{
  if (strlen($content) == 0)
  {
    return '';
  }
  if (checkUTF8($content))
  {
    $output1 = $content;
  }
  else
  {
    $in_charset = mb_detect_encoding($content, mb_detect_order(), true);
    $output1 = false;
    if (!$in_charset)
    {
      $charsets = array('iso-8859-1', 'windows-1251', 'GB2312');
      foreach ($charsets as $charset)
      {
        $output1 = iconv($charset, "UTF-8", $content);
        if ($output1) break;
      }
    } else if ($in_charset != "UTF-8")
    {
      $output1 = iconv($in_charset, "UTF-8", $content);
    }

    if (!$output1 || !checkUTF8($output1)) {
      $output1 = $toHTML ? "<Unknown encoding>" : "<b>Unknown encoding</b>";
    }
  }

  if (!$toHTML) return $output1;
  return (htmlspecialchars($output1, ENT_SUBSTITUTE, "UTF-8")) ?: "<b>Unknown encoding</b>";
}

function checkUTF8($content)
{
  return mb_check_encoding($content, "UTF-8");
}