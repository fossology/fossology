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

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Data\Clearing\AgentClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingResult;
use Fossology\Lib\Data\Clearing\ClearingLicense;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Data\DecisionTypes;

class ClearingDecisionProcessor
{
  const NO_LICENSE_KNOWN_DECISION_TYPE = 2;

  /** @var ClearingDao */
  private $clearingDao;

  /** @var AgentLicenseEventProcessor */
  private $agentLicenseEventProcessor;

  /** @var ClearingEventProcessor */
  private $clearingEventProcessor;

  /** @var DbManager */
  private $dbManager;

  /**
   * @param ClearingDao $clearingDao
   * @param AgentLicenseEventProcessor $agentLicenseEventProcessor
   * @param ClearingEventProcessor $clearingEventProcessor
   */
  public function __construct(ClearingDao $clearingDao, AgentLicenseEventProcessor $agentLicenseEventProcessor, ClearingEventProcessor $clearingEventProcessor, DbManager $dbManager)
  {
    $this->clearingDao = $clearingDao;
    $this->agentLicenseEventProcessor = $agentLicenseEventProcessor;
    $this->clearingEventProcessor = $clearingEventProcessor;
    $this->dbManager = $dbManager;
  }

  public function getUnhandledScannerDetectedLicenses(ItemTreeBounds $itemTreeBounds, $groupId) {
    $events = $this->clearingDao->getRelevantClearingEvents($itemTreeBounds, $groupId);

    $scannerDetectedLicenses = $this->agentLicenseEventProcessor->getScannerDetectedLicenses($itemTreeBounds);

    $clearingLicenseRefs = $this->clearingEventProcessor->getClearingLicenseRefs($events);

    return array_diff_key($scannerDetectedLicenses, $clearingLicenseRefs);
  }

  private function insertClearingEventsForAgentFindings(ItemTreeBounds $itemBounds, $userId, $groupId, $remove = false, $type = ClearingEventTypes::AGENT)
  {
    $eventIds = array();
    foreach($this->agentLicenseEventProcessor->getScannerDetectedLicenses($itemBounds) as $scannerLicenseRef)
    {
      $eventIds[] = $this->clearingDao->insertClearingEvent($itemBounds->getItemId(), $userId, $groupId, $scannerLicenseRef->getLicenseId(), $remove, $type);
    }
    return $eventIds;
  }

  /**
   * @param ClearingDecision $decision
   * @param int $type
   * @param int[] $clearingEventIds
   * @return boolean
   */
  private function clearingDecisionIsDifferentFrom(ClearingDecision $decision, $type, $clearingEventIds)
  {
    $clearingEvents = $decision->getClearingEvents();
    if (count($clearingEvents) != count($clearingEventIds))
      return true;

    foreach($clearingEvents as $clearingEvent) {
      if (false === array_search($clearingEvent->getEventId(), $clearingEventIds))
        return true;
    }
    return $type !== $decision->getType();
  }

  /**
   * @param ItemTreeBounds $itemBounds
   * @param int $userId
   * @param int $type
   * @param boolean $global
   */
  public function makeDecisionFromLastEvents(ItemTreeBounds $itemBounds, $userId, $groupId, $type, $global)
  {
    if ($type < self::NO_LICENSE_KNOWN_DECISION_TYPE)
    {
      return;
    }

    $needTransaction = !$this->dbManager->isInTransaction();
    if ($needTransaction) $this->dbManager->begin();

    $itemId = $itemBounds->getItemId();

    if ($type === self::NO_LICENSE_KNOWN_DECISION_TYPE)
    {
      $type = DecisionTypes::IDENTIFIED;
      $clearingEventIds = $this->insertClearingEventsForAgentFindings($itemBounds, $userId, $groupId, true, ClearingEventTypes::USER);
    } else {
      $clearingEventIds = array();
      foreach(
       $this->clearingDao->getRelevantClearingEvents($itemBounds, $groupId)
       as
       $clearingEvent
      ) {
        $clearingEventIds[] = $clearingEvent->getEventId();
      }
    }

    $currentDecision = $this->clearingDao->getRelevantClearingDecision($itemBounds, $groupId);

    if (null === $currentDecision || $this->clearingDecisionIsDifferentFrom($currentDecision, $type, $clearingEventIds))
    {
      $scope = $global ? DecisionScopes::REPO : DecisionScopes::ITEM;
      $this->clearingDao->createDecisionFromEvents($itemBounds->getItemId(), $userId, $groupId, $type, $scope,
      $clearingEventIds);
    }
    else
    {
      $this->clearingDao->removeWipClearingDecision($itemId, $groupId);
    }

    if ($needTransaction) $this->dbManager->commit();
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @return array
   */
  public function getCurrentClearings(ItemTreeBounds $itemTreeBounds, $groupId)
  {
    $itemId = $itemTreeBounds->getItemId();
    $scannedLicenseDetails = $this->agentLicenseEventProcessor->getScannerDetectedLicenseDetails($itemTreeBounds);
    $agentLicenseRefs = $this->agentLicenseEventProcessor->getScannedLicenses($scannedLicenseDetails);

    $events = $this->clearingDao->getRelevantClearingEvents($itemTreeBounds, $groupId);
    $selection = $this->clearingEventProcessor->getClearingLicenses($events);

    $addedResults = array();
    $removedResults = array();

    foreach (array_merge(array_keys($selection), array_keys($agentLicenseRefs)) as $shortName)
    {
      $licenseDecisionEvent = array_key_exists($shortName, $events) ? $events[$shortName] : null;
      $agentClearingEvents = $this->collectAgentDetectedLicenses($shortName, $scannedLicenseDetails);

      if (($licenseDecisionEvent === null) && (count($agentClearingEvents) == 0))
        continue;

      $licenseDecisionResult = new ClearingResult($licenseDecisionEvent, $agentClearingEvents);
      if (!array_key_exists($shortName, $selection) && $licenseDecisionEvent === null) {
        $addedResults[$shortName] = $licenseDecisionResult;
      } else if ($licenseDecisionEvent !== null)
      {
        if ($licenseDecisionEvent->isRemoved()) {
          $removedResults[$shortName] = $licenseDecisionResult;
        } else {
          $addedResults[$shortName] = $licenseDecisionResult;
        }
      }
    }

    return array($addedResults, $removedResults);
  }

  /**
   * @param $licenseProperty
   * @return AgentClearingEvent
   */
  private function createAgentClearingEvent($licenseProperty)
  {
    return new AgentClearingEvent(
        $licenseProperty['licenseRef'],
        $licenseProperty['agentRef'],
        $licenseProperty['matchId'],
        array_key_exists('percentage', $licenseProperty) ? $licenseProperty['percentage'] : null
    );
  }

  /**
   * @param $licenseShortName
   * @param $agentDetectedLicenses
   * @return array
   */
  private function collectAgentDetectedLicenses($licenseShortName, $agentDetectedLicenses)
  {
    $agentClearingEvents = array();
    if (array_key_exists($licenseShortName, $agentDetectedLicenses))
    {
      foreach ($agentDetectedLicenses[$licenseShortName] as $agentName => $licenseProperties)
      {
        foreach ($licenseProperties as $licenseProperty)
        {
          $agentClearingEvents[] = $this->createAgentClearingEvent($licenseProperty);
        }
      }
    }
    return $agentClearingEvents;
  }


}