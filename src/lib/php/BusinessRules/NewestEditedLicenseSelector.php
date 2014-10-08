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

/**
 * Class NewestEditedLicenseSelector
 * @package Fossology\Lib\BusinessRules
 */
class NewestEditedLicenseSelector extends Object
{

  function __construct()
  {
  }


  /**
   * @param ClearingDecision[] $editedLicensesArray
   * @return ClearingDecision[]
   */
  public function extractGoodClearingDecisionsPerFileID($editedLicensesArray)
  {
    $editedLicensesArrayGrouped = array();
    foreach ($editedLicensesArray as $editedLicense)
    {
      $editedLicensesArrayGrouped[$editedLicense->getPfileId()][] = $editedLicense;
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
    foreach ($licensesPerId as $fileID => $licInfo)
    {
      foreach ($licInfo->getLicenses() as $licRef)
      {
        /** @var LicenseRef $licRef */
        if (!($licRef->getRemoved()))
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
    return $clearingDecWithLicAndContext->getType() !== ClearingDecision::IDENTIFIED or ($clearingDecWithLicAndContext->getScope() == 'upload' and $clearingDecWithLicAndContext->getSameFolder() === false);
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
    foreach ($sortedClearingDecArray as $clearingDecWithLicenses)
    {
      if ($clearingDecWithLicenses->getType() == ClearingDecision::IDENTIFIED and $clearingDecWithLicenses->getSameFolder() and $clearingDecWithLicenses->getScope() == 'upload')
      {
        return $clearingDecWithLicenses;
      }
    }
    foreach ($sortedClearingDecArray as $clearingDecWithLicenses)
    {
      if ($clearingDecWithLicenses->getType() == ClearingDecision::IDENTIFIED and $clearingDecWithLicenses->getScope() == 'global')
      {
        return $clearingDecWithLicenses;
      }
    }
    return null;
  }
}
