<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: J.Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Util;

use Monolog\Logger;

class DataTablesUtility
{
  /**
   * @var Logger
   */
  private $logger;

  function __construct()
  {
    $this->logger = new Logger(self::class);
  }

  /**
   * @param array $inputArray
   * @param string[] $columNamesInDatabase
   * @return null|string[]
   */
  public function getSortingParametersFromArray($inputArray, $columNamesInDatabase, $defaultSearch = array())
  {
    if (array_key_exists('iSortingCols', $inputArray)) {
      if ($inputArray['iSortingCols'] > count($columNamesInDatabase)) {
        $this->logger->warning(
          "did have enough columNames for " . $inputArray['iSortingCols'] .
          " sort columns.");
        return null;
      }
      return $this->getSortingParametersFromArrayImpl($inputArray,
        $columNamesInDatabase, $defaultSearch);
    } else {
      $this->logger->warning("did not find iSortingCols in inputArray");
      return null;
    }
  }


  /**
   * @param array $inputArray
   * @param string[] $columNamesInDatabase
   * @param array $defaultSearch mapping colNumber -> order
   * @return string[]
   */
  private function getSortingParametersFromArrayImpl($inputArray, $columNamesInDatabase, $defaultSearch = array())
  {
    $orderArray = array();
    $sortedCols = array();
    for ($i = 0; $i < $inputArray['iSortingCols']; $i ++) {
      $whichCol = 'iSortCol_' . $i;
      $colNumber = $inputArray[$whichCol];
      $sortedCols[] = intval($colNumber);

      $isSortable = $inputArray['bSortable_' . $colNumber];
      if ($isSortable !== "true") {
        continue;
      }
      $name = $columNamesInDatabase[$colNumber];

      $whichDir = 'sSortDir_' . $i;
      $order = $this->sanitizeSortDirection($inputArray[$whichDir]);
      $orderArray[] = $name . " " . $order;
    }

    foreach ($defaultSearch as $search) {
      $colNumber = $search[0];
      $order = $search[1];
      if (in_array($colNumber, $sortedCols)) {
        continue;
      }
      $isSortable = $inputArray['bSortable_' . $colNumber];
      if ($isSortable !== "true") {
        continue;
      }

      $name = $columNamesInDatabase[$colNumber];
      $orderArray[] = $name . " " . $this->sanitizeSortDirection($order);
    }
    return $orderArray;
  }

  /**
   * @brief Validate sort direction for SQL ORDER BY.
   *
   * Only "asc" and "desc" are accepted. Any other value defaults
   * to "ASC" to prevent SQL injection via user-controlled input.
   *
   * @param mixed $direction
   * @return string "ASC" or "DESC"
   */
  private function sanitizeSortDirection($direction)
  {
    return (is_string($direction) && strcasecmp($direction, 'desc') === 0) ? 'DESC' : 'ASC';
  }


  /**
   * @param array $inputArray
   * @param string[] $columNamesInDatabase
   * @param type $defaultSearch
   * @return string
   */
  public function getSortingString($inputArray, $columNamesInDatabase, $defaultSearch = array())
  {
    $orderArray = $this->getSortingParametersFromArray($inputArray, $columNamesInDatabase, $defaultSearch);
    return empty($orderArray) ? "" : "ORDER BY " . implode(", ", $orderArray);
  }
}
