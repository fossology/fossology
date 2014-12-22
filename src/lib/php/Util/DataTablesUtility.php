<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
 * Author: J.Najjar
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

namespace Fossology\Lib\Util;

use Monolog\Logger;

class DataTablesUtility extends Object
{
  /**
   * @var Logger
   */
  private $logger;

  function __construct()
  {
    $this->logger = new Logger(self::className());
  }

  /**
   * @param array $inputArray
   * @param string[] $columNamesInDatabase
   * @return null|string[]
   */
  public function getSortingParametersFromArray($inputArray, $columNamesInDatabase, $defaultSearch = array())
  {
    if (array_key_exists('iSortingCols', $inputArray))
    {
      if ($inputArray['iSortingCols'] > count($columNamesInDatabase))
      {
        $this->logger->addWarning("did have enough columNames for " . $inputArray['iSortingCols'] . " sort columns.");
        return null;
      }
      return $this->getSortingParametersFromArrayImpl($inputArray, $columNamesInDatabase, $defaultSearch);

    } else
    {
      $this->logger->addWarning("did not find iSortingCols in inputArray");
      return null;
    }
  }


  /**
   * @param array $inputArray
   * @param string[] $columNamesInDatabase
   * @return string[]
   */
  private function getSortingParametersFromArrayImpl($inputArray, $columNamesInDatabase, $defaultSearch = array())
  {

    $orderArray = array();
    $sortedCols = array();
    for ($i = 0; $i < $inputArray['iSortingCols']; $i++)
    {

      $whichCol = 'iSortCol_' . $i;
      $colNumber = $inputArray[$whichCol];
      $sortedCols[] = intval($colNumber);


      $isSortable = $inputArray['bSortable_' . $i];
      if ($isSortable !== "true") continue;

      $name = $columNamesInDatabase[$colNumber];

      $whichDir = 'sSortDir_' . $i;
      $order = $inputArray[$whichDir];
      $orderArray[] = $name . " " . $order;
    }

    foreach ($defaultSearch as $search)
    {
      $colNumber = $search[0];
      $order = $search[1];
      if (in_array($colNumber, $sortedCols)) continue;
      $isSortable = $inputArray['bSortable_' . $colNumber];
      if ($isSortable !== "true") continue;

      $name = $columNamesInDatabase[$colNumber];
      $orderArray[] = $name . " " . $order;
    }
    return $orderArray;
  }


  public function getSortingString($inputArray, $columNamesInDatabase, $defaultSearch = array())
  {

    $orderArray = $this->getSortingParametersFromArray($inputArray, $columNamesInDatabase, $defaultSearch);
    $orderString = "";
    if (!empty($orderArray))
    {
      $orderString .= "ORDER BY ";
      $orderString .= implode(", ", $orderArray);
    }
    return $orderString;
  }

}