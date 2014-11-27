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

  /** @param ClearingDecision[] $clearingDecisions
   * @return \Fossology\Lib\Data\ClearingDecision[]
   */
  public function filterRelevantClearingDecisions($clearingDecisions)
  {
    /** @var ClearingDecision[] $relevantClearingDecisions */
    $relevantClearingDecisions = array();
    foreach ($clearingDecisions as $clearingDecision)
    {
      if ($clearingDecision->getScope() === DecisionScopes::ITEM && !$clearingDecision->getSameFolder())
      {
        continue;
      }
      $relevantClearingDecisions[] = $clearingDecision;
    }
    return $relevantClearingDecisions;
  }

  /**
   * @param ClearingDecision[] $clearingDecisions sorted by newer decision comes before
   * @return ClearingDecision[][] indexed by pfileId and than itemId
   */
  public function filterCurrentClearingDecisions($clearingDecisions)
  {
    $clearingDecisions = $this->filterRelevantClearingDecisions($clearingDecisions);

    /** @var ClearingDecision[][] $clearingDecisionsByItemId */
    $clearingDecisionsMapped = array();

    foreach ($clearingDecisions as $clearingDecision)
    {
      $itemId = $clearingDecision->getUploadTreeId();
      $fileId = $clearingDecision->getPfileId();
      $scope = $clearingDecision->getScope();

      $alreadyExistingInScopeFile = array_key_exists($fileId, $clearingDecisionsMapped)
         ? $clearingDecisionsMapped[$fileId][self::KEYREPO]->getScope()
         : false;
      $alreadyExistingInScopeItem = ((false !== $alreadyExistingInScopeFile) && array_key_exists($fileId, $clearingDecisionsMapped))
         ? $clearingDecisionsMapped[$fileId][$itemId]->getScope()
         : false;

      if ($alreadyExistingInScopeItem === DecisionScopes::ITEM)
      {
        continue;
      }

      switch ($scope)
      {
        case DecisionScopes::ITEM:
          if ($alreadyExistingInScopeItem === false)
          {
            $clearingDecisionsMapped[$fileId][$itemId] = $clearingDecision;
          }
          break;

        case DecisionScopes::REPO:
          if ($alreadyExistingInScopeFile === false)
          {
            $clearingDecisionsMapped[$fileId][self::KEYREPO] = $clearingDecision;
          }

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
      $scope = $clearingDecision->getScope();

      if (array_key_exists($itemId, $clearingDecisionsByItemId) || $scope === DecisionScopes::ITEM)
      {
        continue;
      }

      $clearingDecisionsByItemId[$itemId] = $clearingDecision;
    }
    return $clearingDecisionsByItemId;
  }
} 