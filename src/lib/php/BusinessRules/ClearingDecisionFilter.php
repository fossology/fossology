<?php
/*
 SPDX-FileCopyrightText: © 2014-2017, 2020 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;

/**
 * @class ClearingDecisionFilter
 * @brief Various utility functions to filter ClearingDecision
 */
class ClearingDecisionFilter
{
  /** @var string KEYREPO
   * Key for repo level decisions */
  const KEYREPO = "all";

  /**
   * @brief Get the clearing decisions as a map of
   * `[<pfile-id>] => [<uploadtree-id>] => decision`
   *
   * Irrelevant decisions are removed from the map.
   * @param ClearingDecision[] $clearingDecisions Clearing decisions to be filtered.
   * @return ClearingDecision[][]
   */
  public function filterCurrentClearingDecisions($clearingDecisions)
  {
    /* @var ClearingDecision[][] $clearingDecisionsByItemId */
    $clearingDecisionsMapped = array();

    foreach ($clearingDecisions as $clearingDecision) {
      if ($clearingDecision->getType() == DecisionTypes::IRRELEVANT) {
        continue;
      }
      $itemId = $clearingDecision->getUploadTreeId();
      $fileId = $clearingDecision->getPfileId();
      $scope = $clearingDecision->getScope();

      switch ($scope) {
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


  /**
   * @brief Get clearing decision as map of `<item-id> => <license-shortnames>`
   *
   * Irrelevant decisions and removed licenses are removed from the map.
   * @param ClearingDecision[] $clearingDecisions Clearing decisions to be filtered.
   * @return ClearingDecision[]
   */
  public function filterCurrentClearingDecisionsForLicenseList($clearingDecisions)
  {
    $clearingDecisionsForLicList = array();

    foreach ($clearingDecisions as $clearingDecision) {

      if ($clearingDecision->getType() == DecisionTypes::IRRELEVANT) {
        continue;
      }

      foreach ($clearingDecision->getClearingLicenses() as $clearingLicense) {
        if ($clearingLicense->isRemoved()) {
          continue;
        }
        $itemId = $clearingDecision->getUploadTreeId();
        $clearingDecisionsForLicList[$itemId][] = $clearingLicense->getShortName();
      }
    }
    return $clearingDecisionsForLicList;
  }


  /**
   * @brief Map clearing decisions by upload tree item id
   * @param ClearingDecision[] $clearingDecisions Clearing decisions to be filtered.
   * @return ClearingDecision[]
   */
  public function filterCurrentReusableClearingDecisions($clearingDecisions)
  {
    /** @var ClearingDecision[] $clearingDecisionsByItemId */
    $clearingDecisionsByItemId = array();
    foreach ($clearingDecisions as $clearingDecision) {
      $itemId = $clearingDecision->getUploadTreeId();
      $clearingDecisionsByItemId[$itemId] = $clearingDecision;
    }
    return $clearingDecisionsByItemId;
  }

  /**
   * @brief For a given decision map, get the decision of the given item or
   * pfile id
   * @return ClearingDecision|false ClearingDecision if found, false otherwise.
   */
  public function getDecisionOf($decisionMap, $itemId, $pfileId)
  {
    if (array_key_exists($pfileId, $decisionMap)) {
      $pfileMap = $decisionMap[$pfileId];
      if (array_key_exists($itemId, $pfileMap)) {
        return $pfileMap[$itemId];
      }
      if (array_key_exists(self::KEYREPO, $pfileMap)) {
        return $pfileMap[self::KEYREPO];
      }
    }

    return false;
  }

  /**
   * @brief Get clearing decision as map of `<item-id> => <license-shortnames>`
   * for copyright list
   *
   * Irrelevant decisions and removed licenses are marked as `"Void"`.
   * @param ClearingDecision[] $clearingDecisions Clearing decisions to be
   * filtered.
   * @return ClearingDecision[]
   */
  public function filterCurrentClearingDecisionsForCopyrightList($clearingDecisions)
  {
    $clearingDecisionsForCopyList = array();

    foreach ($clearingDecisions as $clearingDecision) {
      $itemId = $clearingDecision->getUploadTreeId();
      $clearingDecisionsForCopyList[$itemId] = array();
      if (in_array($clearingDecision->getType(), [DecisionTypes::IRRELEVANT, DecisionTypes::DO_NOT_USE, DecisionTypes::NON_FUNCTIONAL])) {
        $clearingDecisionsForCopyList[$itemId][] = "Void";
        continue;
      }

      foreach ($clearingDecision->getClearingLicenses() as $clearingLicense) {
        if ($clearingLicense->isRemoved()) {
          continue;
        }
        $clearingDecisionsForCopyList[$itemId][] = $clearingLicense->getShortName();
      }
      if (empty($clearingDecisionsForCopyList[$itemId])) {
        $clearingDecisionsForCopyList[$itemId][] = "Void";
      }
    }
    return $clearingDecisionsForCopyList;
  }
}
