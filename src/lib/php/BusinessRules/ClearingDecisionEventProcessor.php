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
use Fossology\Lib\Data\LicenseDecision\AgentLicenseDecisionEvent;
use Fossology\Lib\Data\LicenseDecision\LicenseDecision;
use Fossology\Lib\Data\LicenseDecision\LicenseDecisionEventBuilder;
use Fossology\Lib\Data\LicenseDecision\LicenseDecisionResult;
use Fossology\Lib\Data\LicenseDecision\LicenseEventTypes;
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
    $events = $this->clearingDao->getRelevantLicenseDecisionEvents($userId, $item);
    $clearingDecision = $this->clearingDao->getRelevantClearingDecision($userId, $item);

    list($added, $removed) = $this->getCurrentLicenseDecisions($itemBounds, $userId);

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
      /** @var LicenseDecisionResult $licenseDecisionResult */
      if (!$licenseDecisionResult->hasLicenseDecisionEvent())
      {
        $licenseId = $licenseDecisionResult->getLicenseId();
        $this->clearingDao->addLicenseDecision($itemBounds->getUploadTreeId(), $userId, $licenseId, LicenseEventTypes::USER, $isGlobal);
        $insertDecision = true;
      }
    }

    if (!$insertDecision)
    {
      foreach (array_merge($added, $removed) as $licenseShortName => $licenseDecisionResult)
      {
        if (!$licenseDecisionResult->hasLicenseDecisionEvent()) continue;

        $entryTimestamp = $licenseDecisionResult->getLicenseDecisionEvent()->getDateTime();

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
      $licenseDecisionEventBuilder = new LicenseDecisionEventBuilder();
      foreach($added as $licenseShortName => $licenseDecisionResult) {
        /** @var LicenseDecisionResult $licenseDecisionResult */
        $isglobal =$licenseDecisionResult->hasLicenseDecisionEvent()? $licenseDecisionResult->getLicenseDecisionEvent()->isGlobal():true;
        $this->clearingDao->removeLicenseDecision($itemBounds->getUploadTreeId(), $userId,
            $licenseDecisionResult->getLicenseId(), $type, $isglobal);
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
      ReportCachePurgeAll();
    }
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $userId
   * @return array
   */
  public function getCurrentLicenseDecisions(ItemTreeBounds $itemTreeBounds, $userId)
  {
    $uploadTreeId = $itemTreeBounds->getUploadTreeId();

    $agentDetectedLicenses = $this->agentLicenseEventProcessor->getLatestAgentDetectedLicenses($itemTreeBounds);

    list($addedLicenses, $removedLicenses) = $this->clearingDao->getCurrentLicenseDecisions($userId, $uploadTreeId);

    $licenseDecisions = array();
    $removedLicenseDecisions = array();

    $allLicenseShortNames = array_unique(array_merge(array_keys($addedLicenses), array_keys($agentDetectedLicenses)));

    foreach ($allLicenseShortNames as $licenseShortName)
    {
      $licenseDecisionEvent = null;
      $agentLicenseDecisionEvents = array();

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
            $agentLicenseDecisionEvents[] = new AgentLicenseDecisionEvent(
                $licenseProperty['licenseRef'],
                $licenseProperty['agentRef'],
                $licenseProperty['matchId'],
                array_key_exists('percentage', $licenseProperty) ? $licenseProperty['percentage'] : null
            );
          }
        }
      }

      if (($licenseDecisionEvent !== null) || (count($agentLicenseDecisionEvents) > 0))
      {
        $licenseDecisionResult = new LicenseDecisionResult($licenseDecisionEvent, $agentLicenseDecisionEvents);

        if (array_key_exists($licenseShortName, $removedLicenses))
        {
          $removedLicenseDecisions[$licenseShortName] = $licenseDecisionResult;
        } else
        {
          $licenseDecisions[$licenseShortName] = $licenseDecisionResult;
        }
      }
    }

    return array($licenseDecisions, $removedLicenseDecisions);
  }

  /**
   * @param $userId
   * @param $itemTreeBounds
   * @param $lastDecisionDate
   * @return array
   */
  public function filterRelevantLicenseDecisionEvents($userId, $itemTreeBounds, $lastDecisionDate)
  {
    list($added, $removed) = $this->getCurrentLicenseDecisions($itemTreeBounds, $userId);

    if ($lastDecisionDate !== null)
    {
      /**
       * @param LicenseDecisionResult $event
       * @return bool
       */
      $filter_since_event = function (LicenseDecisionResult $event) use ($lastDecisionDate)
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
   * @param LicenseDecision[] $added
   * @param LicenseDecision[] $removed
   * @return bool
   */
  public function checkIfAutomaticDecisionCanBeMade($added, $removed)
  {
    $canAutoDecide = true;
    foreach ($added as $event)
    {
      if ($event->getEventType() === LicenseDecisionResult::AGENT_DECISION_TYPE)
      {
        $canAutoDecide = false;
        break;
      }
    }
    return $canAutoDecide;
  }

}