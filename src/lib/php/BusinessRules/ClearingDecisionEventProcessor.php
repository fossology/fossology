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

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Data\Clearing\AgentClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\LicenseClearing;
use Fossology\Lib\Data\Clearing\ClearingEventBuilder;
use Fossology\Lib\Data\Clearing\ClearingResult;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Data\DecisionTypes;

class ClearingDecisionEventProcessor
{
  const NO_LICENSE_KNOWN_DECISION_TYPE = 2;

  /** @var ClearingDao */
  private $clearingDao;

  /** @var AgentLicenseEventProcessor */
  private $agentLicenseEventProcessor;

  /** @var ClearingEventProcessor */
  private $clearingEventProcessor;

  /**
   * @param ClearingDao $clearingDao
   * @param AgentLicenseEventProcessor $agentLicenseEventProcessor
   * @param ClearingEventProcessor $clearingEventProcessor
   */
  public function __construct(ClearingDao $clearingDao, AgentLicenseEventProcessor $agentLicenseEventProcessor, ClearingEventProcessor $clearingEventProcessor)
  {
    $this->clearingDao = $clearingDao;
    $this->agentLicenseEventProcessor = $agentLicenseEventProcessor;
    $this->clearingEventProcessor = $clearingEventProcessor;
  }

  /**
   * @param ItemTreeBounds $itemBounds
   * @param int $userId
   * @param int $type
   * @param boolean $isGlobal
   */
  public function makeDecisionFromLastEvents(ItemTreeBounds $itemBounds, $userId, $type, $isGlobal)
  {
    if ($type < self::NO_LICENSE_KNOWN_DECISION_TYPE)
    {
      return;
    }

    $item = $itemBounds->getUploadTreeId();

    list($lastDecision, $lastType) = $this->getRelevantClearingDecisionParameters($userId, $item);

    $allEvents = $this->clearingDao->getRelevantClearingEvents($userId, $item);

    list($addedLicenses, $removedLicenses) = $this->clearingEventProcessor->getFilteredState($allEvents);

    $currentEvents = $this->clearingEventProcessor->filterEventsByTime($allEvents, $lastDecision);
    list($currentAddedLicenses, $currentRemovedLicenses) = $this->clearingEventProcessor->getFilteredState($currentEvents);

    $agentDetectedLicenses = $this->agentLicenseEventProcessor->getLatestAgentDetectedLicenses($itemBounds);
    $unhandledAgentDetectedLicenses = $this->clearingEventProcessor->getUnhandledLicenses($allEvents, $agentDetectedLicenses);
    $this->addClearingEventsForLicenses($userId, $item, $unhandledAgentDetectedLicenses);
    $addedLicenses = array_merge($addedLicenses, $unhandledAgentDetectedLicenses);

    $insertDecision = $type !== $lastType || count($currentEvents) > 0 || count($unhandledAgentDetectedLicenses) > 0;

    if ($type === self::NO_LICENSE_KNOWN_DECISION_TYPE)
    {
      $insertDecision = true;
      $type = DecisionTypes::IDENTIFIED;
      list($addedLicenses, $currentRemovedLicenses) = $this->createAllRemoveClearingEvents($addedLicenses, $userId, $item);
    }

    if ($insertDecision)
    {
      $this->insertClearingDecision($userId, $item, $type, $isGlobal, $addedLicenses, $currentRemovedLicenses);
    }
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $userId
   * @return array
   */
  public function getCurrentClearings(ItemTreeBounds $itemTreeBounds, $userId)
  {
    $itemId = $itemTreeBounds->getUploadTreeId();
    $agentDetectedLicenses = $this->agentLicenseEventProcessor->getLatestAgentDetectedLicenseDetails($itemTreeBounds);

    $orderedEvents = $this->clearingDao->getRelevantClearingEvents($userId, $itemId);
    $sortedFilteredEvents = $this->clearingEventProcessor->filterEffectiveEvents($orderedEvents);

    list($addedLicenses, $removedLicenses) = $this->clearingEventProcessor->getCurrentClearingState($sortedFilteredEvents);
    $events = $this->clearingEventProcessor->indexByLicenseShortName($sortedFilteredEvents);

    $addedResults = array();
    $removedResults = array();

    $allLicenseShortNames = array_unique(array_merge(array_keys($addedLicenses), array_keys($agentDetectedLicenses)));
    foreach ($allLicenseShortNames as $licenseShortName)
    {
      $licenseDecisionEvent = array_key_exists($licenseShortName, $addedLicenses) ? $events[$licenseShortName] : null;
      $agentClearingEvents = $this->collectAgentDetectedLicenses($licenseShortName, $agentDetectedLicenses);

      if (($licenseDecisionEvent !== null) || (count($agentClearingEvents) > 0))
      {
        $licenseDecisionResult = new ClearingResult($licenseDecisionEvent, $agentClearingEvents);

        if (array_key_exists($licenseShortName, $removedLicenses))
        {
          $removedResults[$licenseShortName] = $licenseDecisionResult;
        } else
        {
          $addedResults[$licenseShortName] = $licenseDecisionResult;
        }
      }
    }

    return array($addedResults, $removedResults);
  }

  /**
   * @param $userId
   * @param $itemTreeBounds
   * @param $lastDecisionDate
   * @return array
   */
  public function filterRelevantClearingEvents($userId, $itemTreeBounds, $lastDecisionDate)
  {
    list($added, $removed) = $this->getCurrentClearings($itemTreeBounds, $userId);

    if ($lastDecisionDate !== null)
    {
      /**
       * @param ClearingResult $event
       * @return bool
       */
      $filter_since_event = function (ClearingResult $event) use ($lastDecisionDate)
      {
        return $event->getDateTime() >= $lastDecisionDate;
      };
      $added = array_filter($added, $filter_since_event);
      $removed = array_filter($removed, $filter_since_event);
      return array($added, $removed);
    }
    return array($added, $removed);
  }

  /**
   * @param LicenseClearing[] $unionedEvents
   * @return bool
   */
  public function checkIfAutomaticDecisionCanBeMade($unionedEvents)
  {
    $canAutoDecide = true;
    foreach ($unionedEvents as $event)
    {
      if ($event->getEventType() === ClearingResult::AGENT_DECISION_TYPE && !$event->isRemoved())
      {
        $canAutoDecide = false;
        break;
      }
    }
    return $canAutoDecide;
  }

  /**
   * @param int $userId
   * @param int $itemId
   * @param LicenseRef[] $licenseRefs
   * @param int $eventType
   */
  protected function addClearingEventsForLicenses($userId, $itemId, $licenseRefs, $eventType=ClearingEventTypes::USER)
  {
    foreach ($licenseRefs as $licenseRef)
    {
        $this->clearingDao->addClearing($itemId, $userId, $licenseRef->getId(), $eventType);
    }
  }

  /**
   * @param ClearingEvent[] $currentEvents
   * @return ClearingEvent[]
   */
  protected function getCurrentRemovedEvents($currentEvents)
  {
    return array_filter($currentEvents, function (ClearingEvent $event)
    {
      return $event->isRemoved();
    });
  }

  /**
   * @param LicenseRef $licenseRef
   * @param bool|false $isRemoved
   * @return ClearingEvent
   */
  private function createTempClearingEvent(LicenseRef $licenseRef, $isRemoved = false)
  {
    $licenseDecisionEventBuilder = new ClearingEventBuilder();
    $licenseDecisionEventBuilder->setRemoved($isRemoved);

    $licenseDecisionEventBuilder->setLicenseRef($licenseRef);
    //we only need the license ID so the builder defaults should suffice for the rest
    return $licenseDecisionEventBuilder->build();
  }

  /**
   * @param LicenseRef[] $licensesToAdd
   * @param $userId
   * @param $item
   * @internal param $type
   * @return array
   */
  protected function createAllRemoveClearingEvents($licensesToAdd, $userId, $item)
  {
    $removedSinceLastDecision = array();
    foreach ($licensesToAdd as $licenseToAdd)
    {
      $this->clearingDao->removeClearing($item, $userId, $licenseToAdd->getId(), ClearingEventTypes::USER);
      $removedSinceLastDecision[$licenseToAdd->getShortName()]
          = $this->createTempClearingEvent($licenseToAdd, true);
    }
    return array(array(), $removedSinceLastDecision);
  }

  /**
   * @param $userId
   * @param $item
   * @return array
   */
  protected function getRelevantClearingDecisionParameters($userId, $item)
  {
    $clearingDecision = $this->clearingDao->getRelevantClearingDecision($userId, $item);

    if ($clearingDecision)
    {
      return array(
          $clearingDecision->getDateAdded(),
          $clearingDecision->getType()
      );
    }
    return array(null, null);
  }

  /**
   * @param $userId
   * @param $itemId
   * @param $type
   * @param $isGlobal
   * @param $addedLicenses
   * @param $removedLicenses
   */
  protected function insertClearingDecision($userId, $itemId, $type, $isGlobal, $addedLicenses, $removedLicenses)
  {
    $this->clearingDao->insertClearingDecision($itemId, $userId, $type, $isGlobal, $addedLicenses, $removedLicenses);
    $this->clearingDao->removeWipClearingDecision($itemId, $userId);
    ReportCachePurgeAll();
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
  protected function collectAgentDetectedLicenses($licenseShortName, $agentDetectedLicenses)
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