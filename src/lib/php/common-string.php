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


/**
 * @param $content
 * @return string
 */
function convertToUTF8($content, $toHTML=true)
{
  $in_charset = mb_detect_encoding($content, mb_detect_order(), true);
  if (!$in_charset)
  {
    $output1 = false;
    $charsets = array('iso-8859-1', 'windows-1251', 'GB2312');
    foreach ($charsets as $charset)
    {
      $output1 = @iconv($charset, "UTF-8", $content);
      if ($output1) break;
    }
  } else if ($in_charset != "UTF-8")
  {
    $output1 = @iconv($in_charset, "UTF-8", $content);
  } else
  {
    $output1 = $content;
  }
  if (!$output1) $output1 = $content;

  if (! $toHTML) return $output1;
  return (@htmlentities($output1)) ?: "<b>Unknown encoding</b>";

}