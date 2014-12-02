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
use Fossology\Lib\BusinessRules\ClearingDecisionCache;

class ClearingDecisionFilter
{
  /**
   * @param ClearingDecision[] $clearingDecisions sorted by newer decision comes before
   * @return ClearingDecisionCache
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
          $clearingDecisionsMapped[$fileId][ClearingDecisionCache::KEYREPO] = $clearingDecision;
          break;

        default:
          throw new \InvalidArgumentException("unhandled clearing decision scope '" . $scope . "'");
      }
    }

    return new ClearingDecisionCache($clearingDecisionsMapped);
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
} 