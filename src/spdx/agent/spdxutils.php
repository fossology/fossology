<?php
/*
 SPDX-FileCopyrightText: © 2016 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Spdx;

use Fossology\Lib\Data\LicenseRef;

/**
 * @class SpdxUtils
 * @brief Utilities for SPDX2
 */
class SpdxUtils
{
  /**
   * @brief For a given set of arguments assign $args[$key1] and $args[$key2]
   *
   * Get the array of arguments and find $key1 and $key2 values and assign
   * to $args[$key1] and $args[$key2].
   * @param string[] $args String array
   * @param string $key1 Key1
   * @param string $key2 Key2
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
   * @brief Add prefix to the license based on SPDX2 standards
   * @param string $license
   * @return string
   */
  static public function addPrefixOnDemand($license)
  {
    if (empty($license) || $license === "NOASSERTION") {
      return "NOASSERTION";
    }

    if (strpos($license, " OR ") !== false) {
      return "(" . $license . ")";
    }

    $license = preg_replace('/[^a-zA-Z0-9\-\_\.\+]/','-',$license);
    if (strpos($license, LicenseRef::SPDXREF_PREFIX) !== false) {
      // License ref can not end with a '+'
      $license = preg_replace('/\+$/', '-or-later', $license);
    }
    return preg_replace('/\+(?!$)/','-',$license);
  }

  /**
   * @brief Add prefix to license keys
   * @param array $licenses
   * @return string[]
   */
  static public function addPrefixOnDemandKeys($licenses)
  {
    $ret = array();
    foreach ($licenses as $license=>$text) {
      $ret[self::addPrefixOnDemand($license)] = $text;
    }
    return $ret;
  }

  /**
   * @brief Add prefix to license list
   * @param array $licenses
   * @return array
   */
  static public function addPrefixOnDemandList($licenses)
  {
    return array_map(function ($license)
    {
      return SpdxUtils::addPrefixOnDemand($license);
    },$licenses);
  }

  /**
   * @brief Implode licenses with "AND" or "OR"
   * @param string[] $licenses
   * @return string
   */
  static public function implodeLicenses($licenses)
  {
    if (!$licenses || !is_array($licenses) || sizeof($licenses) == 0) {
      return "";
    }

    $licenses = self::addPrefixOnDemandList($licenses);
    sort($licenses, SORT_NATURAL | SORT_FLAG_CASE);

    if (count($licenses) == 3 &&
       ($index = array_search("Dual-license",$licenses)) !== false) {
      return $licenses[$index===0?1:0] . " OR " . $licenses[$index===2?1:2];
    } elseif (count($licenses) == 3 &&
        ($index = array_search(LicenseRef::SPDXREF_PREFIX . "Dual-license", $licenses)) !== false) {
      return $licenses[$index===0?1:0] . " OR " . $licenses[$index===2?1:2];
    } else {
      // Add prefixes where needed, enclose statements containing ' OR ' with parentheses
      return implode(" AND ", $licenses);
    }
  }

  /**
   * Clean an array of strings by trimming the elements and removing empty
   * strings.
   * @param string[] $texts Array of texts to be concatenated.
   * @return string[] String array with all trimmed string elements.
   */
  static public function cleanTextArray($texts): array
  {
    if (!$texts || !is_array($texts) || sizeof($texts) == 0) {
      return [];
    }

    sort($texts, SORT_NATURAL | SORT_FLAG_CASE);

    $cleanArray = [];
    foreach ($texts as $text) {
      $text = trim($text);
      if (empty($text)) {
        continue;
      }
      $cleanArray[] = $text;
    }
    return $cleanArray;
  }

  /**
   * Remove empty and 'NOASSERTION' licenses from list.
   * @param string[] $licenses List of licenses.
   * @return array List of licenses removing empty and 'NOASSERTION's.
   */
  public static function removeEmptyLicenses($licenses): array
  {
    $newList = [];
    foreach ($licenses as $license) {
      if (empty($license) || $license === "NOASSERTION") {
        continue;
      }
      $newList[] = $license;
    }
    return $newList;
  }
}
