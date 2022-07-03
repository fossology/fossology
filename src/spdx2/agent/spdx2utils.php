<?php
/*
 SPDX-FileCopyrightText: © 2016 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\SpdxTwo;

/**
 * @class SpdxTwoUtils
 * @brief Utilities for SPDX2
 */
class SpdxTwoUtils
{
  static public $prefix = "LicenseRef-";      ///< Prefix to be used

  /**
   * @brief For a given set of arguments assign $args[$key1] and $args[$key2]
   *
   * Get the array of arguments and find $key1 and $key2 values and assign
   * to $args[$key1] and $args[$key2].
   * @param string $args String array
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
   * @param callable $spdxValidityChecker
   * @return string
   */
  static public function addPrefixOnDemand($license, $spdxValidityChecker = null)
  {
    if (empty($license) || $license === "NOASSERTION") {
      return "NOASSERTION";
    }

    if (strpos($license, " OR ") !== false) {
      return "(" . $license . ")";
    }

    if ($spdxValidityChecker === null ||
        (is_callable($spdxValidityChecker) &&
            call_user_func($spdxValidityChecker, $license))) {
      return $license;
    }

    // if we get here, we're using a non-standard SPDX license
    // make sure our license text conforms to the SPDX specifications
    $license = preg_replace('/[^a-zA-Z0-9\-\_\.\+]/','-',$license);
    $license = preg_replace('/\+(?!$)/','-',$license);
    return self::$prefix . $license;
  }

  /**
   * @brief Add prefix to license keys
   * @param array $licenses
   * @param callable $spdxValidityChecker
   * @return string[]
   */
  static public function addPrefixOnDemandKeys($licenses, $spdxValidityChecker = null)
  {
    $ret = array();
    foreach ($licenses as $license=>$text) {
      $ret[self::addPrefixOnDemand($license, $spdxValidityChecker)] = $text;
    }
    return $ret;
  }

  /**
   * @brief Add prefix to license list
   * @param array $licenses
   * @param callable $spdxValidityChecker
   * @return array
   */
  static public function addPrefixOnDemandList($licenses, $spdxValidityChecker = null)
  {
    return array_map(function ($license) use ($spdxValidityChecker)
    {
      return SpdxTwoUtils::addPrefixOnDemand($license, $spdxValidityChecker);
    },$licenses);
  }

  /**
   * @brief Implode licenses with "AND" or "OR"
   * @param string $licenses
   * @param callable $spdxValidityChecker
   * @return string
   */
  static public function implodeLicenses($licenses, $spdxValidityChecker = null)
  {
    if (!$licenses || !is_array($licenses) || sizeof($licenses) == 0) {
      return "";
    }

    $licenses = self::addPrefixOnDemandList($licenses, $spdxValidityChecker);
    sort($licenses, SORT_NATURAL | SORT_FLAG_CASE);

    if (count($licenses) == 3 &&
       ($index = array_search("Dual-license",$licenses)) !== false) {
      return $licenses[$index===0?1:0] . " OR " . $licenses[$index===2?1:2];
    } elseif (count($licenses) == 3 &&
        ($index = array_search(self::$prefix . "Dual-license",$licenses)) !== false) {
      return $licenses[$index===0?1:0] . " OR " . $licenses[$index===2?1:2];
    } else {
      // Add prefixes where needed, enclose statments containing ' OR ' with parantheses
      return implode(" AND ", $licenses);
    }
  }
}
