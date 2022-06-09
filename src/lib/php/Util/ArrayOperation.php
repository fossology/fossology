<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Util;

use Closure;

class ArrayOperation
{
  /**
   * @param array
   * @return array
   */
  public static function getMultiplicityOfValues($allValues)
  {
    $uniqueValues = array_unique($allValues);
    $valueMultiplicityMap = array();

    foreach ($uniqueValues as $value) {
      $count = 0;
      foreach ($allValues as $candidate) {
        if ($value == $candidate) {
          $count ++;
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
    for ($offset = 0; $offset < count($values); $offset += $chunkSize) {
      $result = array_merge($result,
        $callback(array_slice($values, $offset, $chunkSize)));
    }
    return $result;
  }

  public static function multiSearch($needles,$haystack)
  {
    foreach ($needles as $needle) {
      $index = array_search($needle, $haystack);
      if ($index !== false) {
        return $index;
      }
    }
    return false;
  }
}
