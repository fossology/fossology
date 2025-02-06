<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\Clearing\ClearingResult;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Exception;

/**
 * @class ClearingDecisionProcessor
 * @brief Utility functions to process ClearingDecision
 */
class ClearingDecisionProcessor
{
  /**
   * @var integer NO_LICENSE_KNOWN_DECISION_TYPE
   * Decision type when no known license found
   */
  const NO_LICENSE_KNOWN_DECISION_TYPE = 2;

  /** @var ClearingDao $clearingDao
   * Clearing DAO object */
  private $clearingDao;
  /** @var AgentLicenseEventProcessor $agentLicenseEventProcessor
   * License event processor object */
  private $agentLicenseEventProcessor;
  /** @var ClearingEventProcessor $clearingEventProcessor
   * Clearing event processor object */
  private $clearingEventProcessor;
  /** @var DbManager $dbManager
   * DB manager */
  private $dbManager;

  /**
   * Constructor
   * @param ClearingDao $clearingDao
   * @param AgentLicenseEventProcessor $agentLicenseEventProcessor
   * @param ClearingEventProcessor $clearingEventProcessor
   * @param DbManager $dbManager
   */
  public function __construct(ClearingDao $clearingDao, AgentLicenseEventProcessor $agentLicenseEventProcessor, ClearingEventProcessor $clearingEventProcessor, DbManager $dbManager)
  {
    $this->clearingDao = $clearingDao;
    $this->agentLicenseEventProcessor = $agentLicenseEventProcessor;
    $this->clearingEventProcessor = $clearingEventProcessor;
    $this->dbManager = $dbManager;
  }

  /**
   * Check if the given upload tree bound have unhandled license detections
   * @param ItemTreeBounds $itemTreeBounds Upload tree bound to check
   * @param int $groupId Current group id
   * @param array $additionalEventIds Additional event ids to include, indexed
   *        by licenseId
   * @param null|LicenseMap $licenseMap If given then license are considered as
   *        equal iff mapped to same
   * @return bool True if unhandled licenses found, false otherwise.
   */
  public function hasUnhandledScannerDetectedLicenses(ItemTreeBounds $itemTreeBounds, $groupId, $additionalEventIds = array(), $licenseMap=null)
  {
    if (!empty($licenseMap) && !($licenseMap instanceof LicenseMap)) {
      throw new Exception('invalid license map');
    }
    $userEvents = $this->clearingDao->getRelevantClearingEvents($itemTreeBounds, $groupId);
    $usageId = empty($licenseMap) ? LicenseMap::TRIVIAL : $licenseMap->getUsage();
    $scannerDetectedEvents = $this->agentLicenseEventProcessor->getScannerEvents($itemTreeBounds,$usageId);
    $eventLicenceIds = array();
    foreach (array_keys($userEvents) as $licenseId) {
      $eventLicenceIds[empty($licenseMap)? $licenseId: $licenseMap->getProjectedId($licenseId)] = $licenseId;
    }
    foreach (array_keys($additionalEventIds) as $licenseId) {
      $eventLicenceIds[empty($licenseMap)? $licenseId: $licenseMap->getProjectedId($licenseId)] = $licenseId;
    }
    foreach (array_keys($scannerDetectedEvents) as $licenseId) {
      if (!array_key_exists(empty($licenseMap)? $licenseId: $licenseMap->getProjectedId($licenseId), $eventLicenceIds)) {
        return true;
      }
    }
    return false;
  }

  /**
   * @brief Insert clearing events in DB for agent findings
   * @param ItemTreeBounds $itemBounds  Upload tree bound of the item
   * @param int $userId   Current user id
   * @param int $groupId  Current group id
   * @param boolean $remove Is the license finding removed?
   * @param int $type     Finding type
   * @param array $removedIds Licenses to be skipped
   * @return number[]
   */
  private function insertClearingEventsForAgentFindings(ItemTreeBounds $itemBounds, $userId, $groupId, $remove = false, $type = ClearingEventTypes::AGENT, $removedIds = array())
  {
    $eventIds = array();
    foreach ($this->agentLicenseEventProcessor->getScannerEvents($itemBounds) as $licenseId => $scannerEvents) {
      if (array_key_exists($licenseId, $removedIds)) {
        continue;
      }
      $scannerLicenseRef = $scannerEvents[0]->getLicenseRef();
      $eventIds[$scannerLicenseRef->getId()] = $this->clearingDao->insertClearingEvent($itemBounds->getItemId(), $userId, $groupId, $scannerLicenseRef->getId(), $remove, $type);
    }
    return $eventIds;
  }

  /**
   * @brief Check if clearing decisions are different from clearing event ids
   * @param ClearingDecision $decision  Clearing decisions to check
   * @param int $type   Clearing decision type required
   * @param int $scope  Clearing decision scope required
   * @param array $clearingEventIds     Clearing events to compare with
   * @return boolean True if they are same, false otherwise
   */
  private function clearingDecisionIsDifferentFrom(ClearingDecision $decision, $type, $scope, $clearingEventIds)
  {
    $clearingEvents = $decision->getClearingEvents();
    if (count($clearingEvents) != count($clearingEventIds)) {
      return true;
    }

    foreach ($clearingEvents as $clearingEvent) {
      if (false === array_search($clearingEvent->getEventId(), $clearingEventIds)) {
        return true;
      }
    }
    return ($type !== $decision->getType()) || ($scope !== $decision->getScope());
  }

  /**
   * @brief Create clearing decisions from clearing events
   * @param ItemTreeBounds $itemBounds
   * @param int $userId
   * @param int $groupId
   * @param int $type
   * @param boolean $global
   * @param array Additional event ids to include, indexed by licenseId
   */
  public function makeDecisionFromLastEvents(ItemTreeBounds $itemBounds, $userId, $groupId, $type, $global, $additionalEventIds = array(), $autoConclude = false)
  {
    if ($type < self::NO_LICENSE_KNOWN_DECISION_TYPE) {
      return;
    }
    $this->dbManager->begin();

    $itemId = $itemBounds->getItemId();
    $includeSubFolders = false;
    if (($global == DecisionScopes::REPO) && ($type != self::NO_LICENSE_KNOWN_DECISION_TYPE)) {
      $includeSubFolders = true;
    }
    $previousEvents = $this->clearingDao->getRelevantClearingEvents($itemBounds, $groupId, $includeSubFolders);
    if ($type === self::NO_LICENSE_KNOWN_DECISION_TYPE) {
      $type = DecisionTypes::IDENTIFIED;
      $clearingEventIds = $this->insertClearingEventsForAgentFindings($itemBounds, $userId, $groupId, true, ClearingEventTypes::USER);
      foreach ($previousEvents as $eventId => $clearingEvent) {
        if (!in_array($eventId, $clearingEventIds) && !$clearingEvent->isRemoved()) {
          $licenseId = $clearingEvent->getLicenseId();
          $newEventId = $this->clearingDao->insertClearingEvent($itemBounds->getItemId(), $userId, $groupId, $licenseId, true);
          $clearingEventIds[$licenseId] = $newEventId;
        }
      }
    } else {
      $clearingEventIds = $this->insertClearingEventsForAgentFindings($itemBounds, $userId, $groupId, false,
        $autoConclude ? ClearingEventTypes::AUTO : ClearingEventTypes::AGENT, $previousEvents);
      foreach ($previousEvents as $clearingEvent) {
        $clearingEventIds[$clearingEvent->getLicenseId()] = $clearingEvent->getEventId();
      }
    }

    $currentDecision = $this->clearingDao->getRelevantClearingDecision($itemBounds, $groupId);
    $clearingEventIds = array_unique(array_merge($clearingEventIds, $additionalEventIds));

    $scope = $global ? DecisionScopes::REPO : DecisionScopes::ITEM;
    if (null === $currentDecision || $this->clearingDecisionIsDifferentFrom($currentDecision, $type, $scope, $clearingEventIds)) {
      $this->clearingDao->createDecisionFromEvents($itemBounds->getItemId(), $userId, $groupId, $type, $scope,
          $clearingEventIds);
    } else {
      $this->clearingDao->removeWipClearingDecision($itemId, $groupId);
    }

    $this->dbManager->commit();
  }

  /**
   * @brief For a given item, get the clearing decisions
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @param int $usageId
   * @return array Array of added and removed license findings
   * @throws Exception
   */
  public function getCurrentClearings(ItemTreeBounds $itemTreeBounds, $groupId, $usageId=LicenseMap::TRIVIAL)
  {
    $agentEvents = $this->agentLicenseEventProcessor->getScannerEvents($itemTreeBounds, $usageId);
    $events = $this->clearingDao->getRelevantClearingEvents($itemTreeBounds, $groupId);

    $addedResults = array();
    $removedResults = array();

    foreach (array_unique(array_merge(array_keys($events), array_keys($agentEvents))) as $licenseId) {
      $licenseDecisionEvent = array_key_exists($licenseId, $events) ? $events[$licenseId] : null;
      $agentClearingEvents = array_key_exists($licenseId, $agentEvents) ? $agentEvents[$licenseId] : array();

      if (($licenseDecisionEvent === null) && (count($agentClearingEvents) == 0)) {
        throw new Exception('not in merge');
      }
      $licenseDecisionResult = new ClearingResult($licenseDecisionEvent, $agentClearingEvents);
      if ($licenseDecisionResult->isRemoved()) {
        $removedResults[$licenseId] = $licenseDecisionResult;
      } else {
        $addedResults[$licenseId] = $licenseDecisionResult;
      }
    }

    return array($addedResults, $removedResults);
  }
}
