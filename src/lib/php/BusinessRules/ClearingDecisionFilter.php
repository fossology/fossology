<?php
/*
Copyright (C) 2014, Siemens AG

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
use Fossology\Lib\Data\DecisionScopes;

class ClearingDecisionFilter
{
  const KEYREPO = "all";
  
  /**
   * @param ClearingDecision[] $clearingDecisions
   * @return ClearingDecision[][]
   */
  public function filterCurrentClearingDecisions($clearingDecisions)
  {
    /** @var ClearingDecision[][] $clearingDecisionsByItemId */
    $clearingDecisionsMapped = array();

    foreach ($clearingDecisions as $clearingDecision)
    {
      $itemId = $clearingDecision->getUploadTreeId();
      $fileId = $clearingDecision->getPfileId();
      $scope = $clearingDecision->getScope();

      switch ($scope)
      {
        case DecisionScopes::ITEM:
          $clearingDecisionsMapped[$fileId][$itemId] = $clearingDecision;
          break;

        case DecisionScopes::REPO:
          $clearingDecisionsMapped[$fileId][self::KEYREPO] = $clearingDecision;
          break;

        default:
          throw new \InvalidArgumentException("unhandled clearing decision scope '" . $scope . "'");
      }
    }

    return $clearingDecisionsMapped;
  }

  /** @param ClearingDecision[] $clearingDecisions
   * @return ClearingDecision[]
   */
  public function filterCurrentReusableClearingDecisions($clearingDecisions)
  {
    /** @var ClearingDecision[] $clearingDecisionsByItemId */
    $clearingDecisionsByItemId = array();
    foreach ($clearingDecisions as $clearingDecision)
    {
      $itemId = $clearingDecision->getUploadTreeId();
      $clearingDecisionsByItemId[$itemId] = $clearingDecision;
    }
    return $clearingDecisionsByItemId;
  }
  
  
  /**
   * @return ClearingDecision|false
   */
  public function getDecisionOf($decisionMap, $itemId, $pfileId)
  {
    if (array_key_exists($pfileId, $decisionMap))
    {
      $pfileMap = $decisionMap[$pfileId];
      if (array_key_exists($itemId, $pfileMap))
      {
        return $pfileMap[$itemId];
      }
      else
      {
        return $pfileMap[self::KEYREPO];
      }
    }

    return false;
  }

  /**
   * @param ClearingDecision[] $clearingDecisions
   * @return array
   */
  public function getAllLicenseNames($clearingDecisions)
  {
    $result = array();
    $decisionMap = $this->filterCurrentClearingDecisions($clearingDecisions);
    
    foreach($decisionMap as $pFileMap)
    {
      /** @var ClearingDecision $decision */
      foreach($pFileMap as $decision)
      {
        foreach($decision->getPositiveLicenses() as $toAdd)
        {
          array_push($result, $toAdd->getShortName());
        }

      }
    }

    return $result;
  }

  
} 