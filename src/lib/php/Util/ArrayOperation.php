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

  /**
   * @brief Check if a list of keys exists in associative array.
   *
   * This function takes a list of keys which should appear in an associative
   * array. The function flips the key array to make it as an associative array.
   * It then uses the array_diff_key() to compare the two arrays.
   *
   * @param array $array Associative array to check keys against
   * @param array $keys  Array of keys to check
   * @return boolean True if all keys exists, false otherwise.
   * @uses array_flip()
   * @uses array_diff_key()
   */
  public static function arrayKeysExists(array $array, array $keys): bool
  {
    return !array_diff_key(array_flip($keys), $array);
  }
}
