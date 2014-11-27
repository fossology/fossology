<?php
/*
Copyright (C) 2014, Siemens AG
Authors: Daniele Fognini, Johannes Najjar

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

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Util\Object;
use Fossology\Lib\Data\DecisionScopes;

/**
 * @brief filter newest license from array of [ClearingDecisions or LicenceRef array]
 * @package Fossology\Lib\BusinessRules
 */
class LicenseFilter extends Object
{

  /** @var ClearingDecisionFilter */
  private $clearingDecisionFilter;

  public function __construct(ClearingDecisionFilter $clearingDecisionFilter)
  {
    $this->clearingDecisionFilter = $clearingDecisionFilter;
  }

  /**
   * @param ClearingDecision[] $editedDecArray
   * @return LicenseRef[][][]
   *      array of LicenseRef mapped by fileId, itemId
   *
   * @todo refactor all this call stack to return an object
   */
  public function extractGoodLicensesPerItem($editedDecArray)
  {
    $goodLicenses = $this->selectNewestEditedLicensePerItem($editedDecArray);

    return $goodLicenses;
  }

  /**
   * @param CleringDecision[] $editedLicensesArray
   * @return string[]
   */
  public function extractGoodLicenses($editedLicensesArray)
  {
    $licensesPerFileItem = $this->extractGoodLicensesPerItem($editedLicensesArray);

    $licenses = array();
    foreach ($licensesPerFileItem as $licensesPerItem)
    {
      foreach ($licensesPerItem as $licenseRefs)
      {
        /** @var LicenseRef $licenseRef */
        foreach ($licenseRefs as $licenseRef)
        {
          $shortName = $licenseRef->getShortName();
          if (!in_array($shortName, $licenses))
            $licenses[] = $shortName;
        }
      }
    }
    return $licenses;
  }

  /**
   * @brief $sortedClearingDecArray needs to be sorted with the newest clearingDecision first.
   * @param ClearingDecision[] $sortedClearingDecArray
   * @return LicenseRef[][][]
   */
  private function selectNewestEditedLicensePerItem($sortedClearingDecArray)
  {
    $mappedClearingDecisions = $this->clearingDecisionFilter->filterCurrentClearingDecisions($sortedClearingDecArray);

    $mappedLicenses = array();
    foreach ($mappedClearingDecisions as $fileId => $mappedClearingDecision)
    {
      /** @var ClearingDecision $clearingDecision */
      foreach ($mappedClearingDecision as $itemId => $clearingDecision)
      {
        $mappedLicenses[$fileId][$itemId] = $clearingDecision->getPositiveLicenses();
      }
    }
    return $mappedLicenses;
  }

}