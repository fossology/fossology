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
   * @return ClearingDecision[]
   */
  public function extractGoodClearingDecisionsPerFileID($editedLicensesArray)
  {
    $editedLicensesArrayGrouped = array();
    foreach ($editedLicensesArray as $editedLicense)
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
    
    $goodLicenseDecisions = array();
    foreach ($editedLicensesArrayGrouped as $fileId => $editedLicensesArray)
    {
      $cd = $this->selectNewestEditedLicensePerFileID($editedLicensesArray);
      if ($cd != null)
      {
        $goodLicenseDecisions[$fileId] = $cd;
      }
    }

    return $goodLicenseDecisions;
  }

  /**
   * @param ClearingDecision[] $editedLicensesArray
   * @return string[]
   */
  public function extractGoodLicenses($editedLicensesArray)
  {
    $licensesPerId = $this->extractGoodClearingDecisionsPerFileID($editedLicensesArray);

    $licenses = array();
    foreach ($licensesPerId as $licInfo)
    {
      foreach ($licInfo->getPositiveLicenses() as $licRef)
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
   * @return ClearingDecision
   */
  public function selectNewestEditedLicensePerFileID($sortedClearingDecArray)
  {
// $sortedClearingDecArray needs to be sorted with the newest clearingDecision first.
    //! Note that this can not distinguish two files with the same pfileID (hash value) in the same folder, this can yield
    //! misleading folder content overviews in the license browser and in the count of license findings
    $out =array ();
    foreach ($sortedClearingDecArray as $clearingDecision)
    {
      if ($clearingDecision->getType() == DecisionTypes::IDENTIFIED and $clearingDecision->getSameFolder() and $clearingDecision->getScope() == 'upload')
      {
        $utid = $clearingDecision->getUploadTreeId();
        $out[$utid] = $clearingDecision->getPositiveLicenses();
      }
    }
    
    if(count($out) > 0) {
      return $this->flatten($out);
    }
    
    foreach ($sortedClearingDecArray as $clearingDecision)
    {
      if ($clearingDecision->getType() == DecisionTypes::IDENTIFIED and $clearingDecision->getScope() == 'global')
      {
        return $clearingDecision;
      }
    }
    return null;
  }


  /**
   * @param LicenseRef[][] $in
   * @return ClearingDecision
   */
  private function flatten($in)
  {
    $licenses = array();
    foreach ($in as $clearingD) {
     $licenses = array_merge($licenses, $clearingD);
    }
    $out = ClearingDecisionBuilder::create()
            ->setPositiveLicenses(array_unique($licenses) )
            ->setSameUpload(true)
            ->setScope('upload')
            ->setType(DecisionTypes::IDENTIFIED)
            ->build();
    return $out;
  }
        
}