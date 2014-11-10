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
use Fossology\Lib\Data\Clearing\Clearing;
use Fossology\Lib\Data\Clearing\ClearingEventBuilder;
use Fossology\Lib\Data\Clearing\ClearingResult;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Data\DecisionTypes;

class ClearingDecisionEventProcessor
{
  const NO_LICENSE_KNOWN_DECISION_TYPE = 2;

  /** @var ClearingDao */
  private $clearingDao;

  /** @var AgentLicenseEventProcessor */
  private $agentLicenseEventProcessor;

  /**
   * @param ClearingDao $clearingDao
   * @param AgentLicenseEventProcessor $agentLicenseEventProcessor
   */
  public function __construct(ClearingDao $clearingDao, AgentLicenseEventProcessor $agentLicenseEventProcessor)
  {
    $this->clearingDao = $clearingDao;
    $this->agentLicenseEventProcessor = $agentLicenseEventProcessor;
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
    $events = $this->clearingDao->getRelevantClearingEvents($userId, $item);
    $clearingDecision = $this->clearingDao->getRelevantClearingDecision($userId, $item);

    list($added, $removed) = $this->getCurrentClearings($itemBounds, $userId);

    $lastDecision = null;
    $clearingDecType=null;
    if ($clearingDecision)
    {
      $lastDecision = $clearingDecision->getDateAdded();
      $clearingDecType = $clearingDecision->getType();
    }

    $insertDecision = ($type!=$clearingDecType);
    foreach ($added  as $licenseShortName => $licenseDecisionResult)
    {
      /** @var ClearingResult $licenseDecisionResult */
      if (!$licenseDecisionResult->hasClearingEvent())
      {
        $licenseId = $licenseDecisionResult->getLicenseId();
        $this->clearingDao->addClearing($itemBounds->getUploadTreeId(), $userId, $licenseId, ClearingEventTypes::USER);
        $insertDecision = true;
      }
    }

    if (!$insertDecision)
    {
      foreach (array_merge($added, $removed) as $licenseShortName => $licenseDecisionResult)
      {
        if (!$licenseDecisionResult->hasClearingEvent()) continue;

        $entryTimestamp = $licenseDecisionResult->getClearingEvent()->getDateTime();

        if ($lastDecision === null || $lastDecision < $entryTimestamp)
        {
          $insertDecision = true;
          break;
        }
      }
    }
    $removedSinceLastDecision = array();
    foreach ($events as $event)
    {
      $licenseShortName = $event->getLicenseShortName();
      $entryTimestamp = $event->getDateTime();
      if ($event->isRemoved() && !array_key_exists($licenseShortName, $added) && $lastDecision < $entryTimestamp)
      {
        $removedSinceLastDecision[$licenseShortName] = $event;
        $insertDecision = true;
      }
    }

    if ($type === self::NO_LICENSE_KNOWN_DECISION_TYPE)
    {
      $insertDecision = true;
      $removedSinceLastDecision = array();
      $licenseDecisionEventBuilder = new ClearingEventBuilder();
      foreach($added as $licenseShortName => $licenseDecisionResult) {
        $this->clearingDao->removeClearing($itemBounds->getUploadTreeId(), $userId,
            $licenseDecisionResult->getLicenseId(), $type);
        $licenseDecisionEventBuilder
            ->setLicenseRef($licenseDecisionResult->getLicenseRef());
        //we only need the license ID so the builder defaults should suffice for the rest
        $removedSinceLastDecision[$licenseShortName] = $licenseDecisionEventBuilder->build();
      }

      $added = array();
      $type = DecisionTypes::IDENTIFIED;
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
    $uploadTreeId = $itemTreeBounds->getUploadTreeId();
    $agentDetectedLicenses = $this->agentLicenseEventProcessor->getLatestAgentDetectedLicenses($itemTreeBounds);

    list($addedLicenses, $removedLicenses) = $this->clearingDao->getCurrentClearings($userId, $uploadTreeId);

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
   * @param Clearing[] $added
   * @param Clearing[] $removed
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

}