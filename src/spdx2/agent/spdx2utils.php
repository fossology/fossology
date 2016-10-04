<?php
/*
 * Copyright (C) 2016, Siemens AG
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
 */
namespace Fossology\SpdxTwo;

class SpdxTwoUtils
{
  /**
   * @param string[] $args
   * @param string $key1
   * @param string $key2
   *
   * @return string[] $args
   */
  static public function preWorkOnArgsFlp($args,$key1,$key2)
  {
    $needle = ' --'.$key2.'=';
    if (is_array($args) &&
        array_key_exists($key1, $args) &&
        strpos($args[$key1],$needle) !== false) {
      $exploded = explode($needle,$args[$key1]);
      $args[$key1] = trim($exploded[0]);
      $args[$key2] = trim($exploded[1]);
    }
    return $args;
  }

  /**
   * @param string[] $licenses
   * @param string="" $prefix
   *
   * @return string
   */
  static public function implodeLicenses($licenses, $prefix="")
  {
    if(!$licenses || !is_array($licenses) || sizeof($licenses) == 0)
    {
      return "";
    }

    $licenses = array_map(function ($license) use ($prefix)
    {
      if(strpos($license, " OR ") !== false)
      {
        return "(" . $license . ")";
      }
      else
      {
        if(is_array($prefix)){
          $prefix = $prefix[$license];
        }
        if(substr($license, 0, strlen($prefix)) === $prefix)
        {
          return $license;
        }
        else
        {
          return $prefix . $license;
        }
      }
    },$licenses);
    sort($licenses, SORT_NATURAL | SORT_FLAG_CASE);

    if(count($licenses) == 3 &&
       ($index = array_search($prefix . "Dual-license",$licenses)) !== false)
    {
      return $licenses[$index===0?1:0] . " OR " . $licenses[$index===2?1:2];
    }
    else
    {
      // Add prefixes where needed, enclose statments containing ' OR ' with parantheses
      return implode(" AND ", $licenses);
    }
  }
}
