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

namespace Fossology\Lib\Util;

use Closure;

class ArrayOperation extends Object
{
  /**
   * @param array
   * @return array
   */
  public static function getMultiplicityOfValues($allValues)
  {
    $uniqueValues = array_unique($allValues);
    $valueMultiplicityMap = array();

    foreach ($uniqueValues as $value)
    {
      $count = 0;
      foreach ($allValues as $candidate)
      {
        if ($value == $candidate)
        {
          $count++;
        }
      }
      $valueMultiplicityMap[$value] = $count;
    }

    return $valueMultiplicityMap;
  }

  public static function callChunked(Closure $callback, $values, $chunkSize)
  {
    if ($chunkSize <= 0) {
      throw new \InvalidArgumentException('chunk size should be positive');
    }
    $result = array();
    for ($offset = 0; $offset < count($values); $offset += $chunkSize)
    {
      $result = array_merge(
          $result,
          $callback(array_slice($values, $offset, $chunkSize))
      );
    }
    return $result;
  }
  
  
  public static function multiSearch($needles,$haystack){
    foreach($needles as $needle){
      $index = array_search($needle, $haystack);
      if ($index !== false)
      {
        return $index;
      }
    }
    return false;
  }
}