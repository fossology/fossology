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
use Fossology\Lib\Data\ClearingDecisionBuilder;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Util\Object;

/**
 * Class NewestEditedLicenseSelector
 * @package Fossology\Lib\BusinessRules
 */
class NewestEditedLicenseSelector extends Object
{
  /**
   * @param ClearingDecision[] $editedLicensesArray
   * @return LicenseRef[][]
   */
  public function extractGoodLicensesPerFileID($editedDecArray)
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
      $licArr = $this->selectNewestEditedLicensePerFileID($editedLicensesArray);
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
    $licensesPerId = $this->extractGoodLicensesPerFileID($editedLicensesArray);

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
   * @param ClearingDecision $clearingDecWithLicAndContext
   * @return bool
   */
  public function isInactive($clearingDecWithLicAndContext)
  {
    return $clearingDecWithLicAndContext->getType() !== DecisionTypes::IDENTIFIED or ($clearingDecWithLicAndContext->getScope() == 'upload' and $clearingDecWithLicAndContext->getSameFolder() === false);
  }

  // these two functions have to be kept consistent to each other

  /**
   * @param ClearingDecision[] $sortedClearingDecArray
   * @return LicenseRef[]|null
   */
  private function selectNewestEditedLicensePerFileID($sortedClearingDecArray)
  {
// $sortedClearingDecArray needs to be sorted with the newest clearingDecision first.
    //! Note that this can not distinguish two files with the same pfileID (hash value) in the same folder, this can yield
    //! misleading folder content overviews in the license browser and in the count of license findings
    $upload = array();
    $global = array();
    foreach ($sortedClearingDecArray as $clearingDecision)
    {
      if ($clearingDecision->getType() != DecisionTypes::IDENTIFIED)
      {
        continue;
      }
      $utid = $clearingDecision->getUploadTreeId();
      if ($clearingDecision->getSameFolder() && $clearingDecision->getScope() == 'upload' && !array_key_exists($utid, $upload))
      {
        unset($global[$utid]);
        $upload[$utid] = $clearingDecision->getPositiveLicenses();
      }
      if ($clearingDecision->getScope() == 'global' && !array_key_exists($utid, $upload) && !array_key_exists($utid, $global))
      {
        $global[$utid] = $clearingDecision->getPositiveLicenses();
      }
    }
    $licenses = array_merge($global,$upload); // not ($upload,$global)
    if(count($licenses) > 0) {
      return $this->flatten($licenses);
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