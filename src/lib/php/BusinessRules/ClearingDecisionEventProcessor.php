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
    $item = $itemBounds->getUploadTreeId();
    if ($type < self::NO_LICENSE_KNOWN_DECISION_TYPE)
    {
      return;
    }
    $agentDetectedLicenses = $this->agentLicenseEventProcessor->getLatestAgentDetectedLicenses($itemBounds);
    $currentEvents = $this->clearingDao->getRelevantClearingEvents($userId, $item);
    $clearingDecision = $this->clearingDao->getRelevantClearingDecision($userId, $item);

    list($added, $removed) = $this->clearingEventProcessor->getCurrentClearings($currentEvents);

    $lastDecision = null;
    $clearingDecType = null;
    if ($clearingDecision)
    {
      $lastDecision = $clearingDecision->getDateAdded();
      $clearingDecType = $clearingDecision->getType();
    }

    $currentEvents = $this->clearingEventProcessor->filterEventsByTime($currentEvents, $lastDecision);

    $insertDecision = ($type != $clearingDecType && count($currentEvents) > 0);
    $insertDecision |= $this->addClearingEventForAgentDetectedLicenses($userId, $agentDetectedLicenses, $added, $item);

    $removedSinceLastDecision = $this->getCurrentRemovedEvents($currentEvents);

    if ($type === self::NO_LICENSE_KNOWN_DECISION_TYPE)
    {
      $insertDecision = true;
      $type = DecisionTypes::IDENTIFIED;
      list($added, $removedSinceLastDecision) = $this->createAllRemoveClearingEvents($added, $userId, $item);
    }

    if ($insertDecision)
    {
      $this->clearingDao->insertClearingDecision($item, $userId, $type, $isGlobal, $added, $removedSinceLastDecision);
      $this->clearingDao->removeWipClearingDecision($item, $userId);
      ReportCachePurgeAll();
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
    $agentDetectedLicenses = $this->agentLicenseEventProcessor->getLatestAgentDetectedLicenses($itemTreeBounds);

    $currentEvents = $this->clearingDao->getRelevantClearingEvents($userId, $itemId);
    list($addedLicenses, $removedLicenses) = $this->clearingEventProcessor->getCurrentClearings($currentEvents);

    $licenseDecisions = array();
    $removedClearings = array();

    $allLicenseShortNames = array_unique(array_merge(array_keys($addedLicenses), array_keys($agentDetectedLicenses)));

    foreach ($allLicenseShortNames as $licenseShortName)
    {
      $licenseDecisionEvent = null;
      $agentClearingEvents = array();

      if (array_key_exists($licenseShortName, $addedLicenses))
      {
        $licenseDecisionEvent = $addedLicenses[$licenseShortName];
      }

      if (array_key_exists($licenseShortName, $agentDetectedLicenses))
      {
        foreach ($agentDetectedLicenses[$licenseShortName] as $agentName => $licenseProperties)
        {
          foreach ($licenseProperties as $licenseProperty)
          {
            $agentClearingEvents[] = new AgentClearingEvent(
                $licenseProperty['licenseRef'],
                $licenseProperty['agentRef'],
                $licenseProperty['matchId'],
                array_key_exists('percentage', $licenseProperty) ? $licenseProperty['percentage'] : null
            );
          }
        }
      }

      if (($licenseDecisionEvent !== null) || (count($agentClearingEvents) > 0))
      {
        $licenseDecisionResult = new ClearingResult($licenseDecisionEvent, $agentClearingEvents);

        if (array_key_exists($licenseShortName, $removedLicenses))
        {
          $removedClearings[$licenseShortName] = $licenseDecisionResult;
        } else
        {
          $licenseDecisions[$licenseShortName] = $licenseDecisionResult;
        }
      }
    }

    return array($licenseDecisions, $removedClearings);
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
   * @param LicenseClearing[] $added
   * @param LicenseClearing[] $removed
   * @return bool
   */
  public function checkIfAutomaticDecisionCanBeMade($added, $removed)
  {
    $canAutoDecide = true;
    foreach ($added as $event)
    {
      if ($event->getEventType() === ClearingResult::AGENT_DECISION_TYPE)
      {
        $canAutoDecide = false;
        break;
      }
    }
    return $canAutoDecide;
  }

  /**
   * @param $userId
   * @param $agentDetectedLicenses
   * @param $added
   * @param $item
   * @return boolean
   */
  protected function addClearingEventForAgentDetectedLicenses($userId, $agentDetectedLicenses, $added, $item)
  {
    $changed = false;
    foreach ($agentDetectedLicenses as $licenseShortName => $agentLicense)
    {
      if (!array_key_exists($licenseShortName, $added))
      {
        $licenseId = $agentLicense->getLicenseId();
        $this->clearingDao->addClearing($item, $userId, $licenseId, ClearingEventTypes::USER);
        $changed = true;
      }
    }
    return $changed;
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
  private function createTempClearingEvent(LicenseRef $licenseRef, $isRemoved=false)
  {
    $licenseDecisionEventBuilder = new ClearingEventBuilder();
    $licenseDecisionEventBuilder->setRemoved($isRemoved);

    $licenseDecisionEventBuilder->setLicenseRef($licenseRef);
    //we only need the license ID so the builder defaults should suffice for the rest
    return $licenseDecisionEventBuilder->build();
  }

  /**
   * @param $addedClearingEvents
   * @param $userId
   * @param $item
   * @internal param $type
   * @return array
   */
  protected function createAllRemoveClearingEvents($addedClearingEvents, $userId, $item)
  {
    $removedSinceLastDecision = array();
    foreach ($addedClearingEvents as $clearingEvent)
    {
      /** @var ClearingEvent $clearingEvent */
      $this->clearingDao->removeClearing($item, $userId, $clearingEvent->getLicenseId(), ClearingEventTypes::USER);
      $removedSinceLastDecision[$clearingEvent->getLicenseShortName()]
          = $this->createTempClearingEvent($clearingEvent->getLicenseRef(), true);
    }
    return array(array(), $removedSinceLastDecision);
  }


}