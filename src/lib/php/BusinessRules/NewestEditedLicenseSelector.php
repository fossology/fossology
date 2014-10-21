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
use Fossology\Lib\Util\Object;

/**
 * @brief filter newest license from array of [ClearingDecisions or LicenceRef array]
 * @package Fossology\Lib\BusinessRules
 */
class NewestEditedLicenseSelector extends Object
{
  /**
   * @param ClearingDecision[] $editedDecArray
   * @return LicenseRef[][]
   */
  public function extractGoodLicensesPerItem($editedDecArray)
  {
    $editedLicensesArrayGrouped = array();
    foreach ($editedDecArray as $editedLicense)
    {
      $pfileId = $editedLicense->getPfileId();
      if (!array_key_exists($pfileId, $editedLicensesArrayGrouped))
      {
        $editedLicensesArrayGrouped[$pfileId] = array($editedLicense);
      } else
      {
        $editedLicensesArrayGrouped[$pfileId][] = $editedLicense;
      }
    }
    
    $goodLicenses = array();
    foreach ($editedLicensesArrayGrouped as $fileId => $editedLicensesArray)
    {
      $licArr = $this->selectNewestEditedLicensePerItem($editedLicensesArray);
      if ($licArr !== null)
      {
        $goodLicenses[$fileId] = $licArr;
      }
    }

    return $goodLicenses;
  }

  /**
   * @param LicenseRef[][] $editedLicensesArray
   * @return string[]
   */
  public function extractGoodLicenses($editedLicensesArray)
  {
    $licensesPerId = $this->extractGoodLicensesPerItem($editedLicensesArray);

    $licenses = array();
    foreach ($licensesPerId as $licInfo)
    {
      foreach ($licInfo as $licRef)
      {
        $licenses[] = $licRef->getShortName();
      }
    }
    return $licenses;
  }

  /**
   * @brief $sortedClearingDecArray needs to be sorted with the newest clearingDecision first.
   * @param ClearingDecision[] $sortedClearingDecArray
   * @return LicenseRef[]|null
   */
  private function selectNewestEditedLicensePerItem($sortedClearingDecArray)
  {
    $upload = array();
    $global = array();
    foreach ($sortedClearingDecArray as $clearingDecision)
    {
      $utid = $clearingDecision->getUploadTreeId();
      if( array_key_exists($utid, $upload) )
      {
        continue;
      }
      if ($clearingDecision->getSameFolder() && $clearingDecision->getScope() == 'upload')
      {
        unset($global[$utid]);
        $upload[$utid] = $clearingDecision->getPositiveLicenses();
      }
      if ($clearingDecision->getScope() == 'global' && !array_key_exists($utid, $upload) && !array_key_exists($utid, $global))
      {
        $global[$utid] = $clearingDecision->getPositiveLicenses();
      }
    }
    $licenseArrays = array_merge($global,$upload); // not ($upload,$global)
    if(count($licenseArrays) > 0) {
      return $this->flatten($licenseArrays);
    }
    return null;
  }


  /**
   * @param LicenseRef[][] $in
   * @return LicenseRef[]
   */
  private function flatten($in)
  {
    $licenses = array();
    foreach ($in as $clearingD) {
     $licenses = array_merge($licenses, $clearingD);
    }
    $out = array_unique($licenses);
    return $out;
  }
        
}