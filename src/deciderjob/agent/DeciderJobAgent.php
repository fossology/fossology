<?php
/*
 Author: Daniele Fognini
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

namespace Fossology\DeciderJob;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\BusinessRules\AgentLicenseEventProcessor;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Dao\LicenseDao;

define("CLEARING_DECISION_IS_GLOBAL", false);

include_once(__DIR__ . "/version.php");

class DeciderJobAgent extends Agent {
  const FORCE_DECISION = 1;

  /** @var int */
  private $conflictStrategyId;
  /** @var UploadDao */
  private $uploadDao;
  /** @var ClearingDecisionProcessor */
  private $clearingDecisionProcessor;
  /** @var AgentLicenseEventProcessor */
  private $agentLicenseEventProcessor;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var HighlightDao */
  private $highlightDao;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var int */
  private $decisionIsGlobal = CLEARING_DECISION_IS_GLOBAL;
  /** @var DecisionTypes */
  private $decisionTypes;
  /** @var LicenseMap */
  private $licenseMap = null;
  /** @var int */
  private $licenseMapUsage = null;

  function __construct($licenseMapUsage=null)
  {
    parent::__construct(AGENT_DECIDER_JOB_NAME, AGENT_DECIDER_JOB_VERSION, AGENT_DECIDER_JOB_REV);

    $this->uploadDao = $this->container->get('dao.upload');
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->highlightDao = $this->container->get('dao.highlight');
    $this->decisionTypes = $this->container->get('decision.types');
    $this->clearingDecisionProcessor = $this->container->get('businessrules.clearing_decision_processor');
    $this->agentLicenseEventProcessor = $this->container->get('businessrules.agent_license_event_processor');
    $this->licenseDao = $this->container->get('dao.license');

    $this->agentSpecifOptions = "k:";
    $this->licenseMapUsage = $licenseMapUsage;
  }

  function processClearingEventOfCurrentJob()
  {
    $userId = $this->userId;
    $groupId = $this->groupId;
    $jobId = $this->jobId;

    $eventsOfThisJob = $this->clearingDao->getEventIdsOfJob($jobId);
    foreach ($eventsOfThisJob as $uploadTreeId => $additionalEventsFromThisJob)
    {
      $containerBounds = $this->uploadDao->getItemTreeBounds($uploadTreeId);
      foreach($this->loopContainedItems($containerBounds) as $itemTreeBounds)
      {
      	$this->processClearingEventsForItem($itemTreeBounds, $userId, $groupId, $additionalEventsFromThisJob);
      }
    }
  }

  private function loopContainedItems($itemTreeBounds)
  {
    if (!$itemTreeBounds->containsFiles())
    {
      return array($itemTreeBounds);
    }
    $result = array();
    $condition = "(ut.lft BETWEEN $1 AND $2) AND ((ut.ufile_mode & (3<<28)) = 0)";
    $params = array($itemTreeBounds->getLeft(), $itemTreeBounds->getRight());
    foreach($this->uploadDao->getContainedItems($itemTreeBounds, $condition, $params) as $item)
    {
      $result[] = $item->getItemTreeBounds();
    }
    return $result;
  }

  function processUploadId($uploadId)
  {
    $args = $this->args;
    $this->conflictStrategyId = array_key_exists('k', $args) ? $args['k'] : NULL;

    $this->licenseMap = new LicenseMap($this->dbManager, $this->groupId, $this->licenseMapUsage);

    $this->processClearingEventOfCurrentJob();

    return true;
  }

  /**
   * \brief Check if a new clearing decision can be added
   *
   * Clearing is not allowd if:
   *   - concluded license has already been added, or
   *   - agent has detected No_license_found, or
   *   - user has concluded that there is no valid license in a file
   *
   * @param $itemTreeBounds
   * @param $userId
   * @param $groupId
   * @param $eventsFromJob
   * @return boolean
   */
  private function isClearingAllowed($itemTreeBounds, $userId, $groupId, $eventsFromJob)
  {
    $detectedLicenses = $this->licenseDao->getAgentFileLicenseMatches($itemTreeBounds);
    list($concludedLicenses, $removedLicenses) = $this->clearingDecisionProcessor->getCurrentClearings($itemTreeBounds, $groupId, LicenseMap::CONCLUSION);
    if (!is_null($concludedLicenses))
    {
      foreach ($concludedLicenses as $license)
      {
        $event = $license->getClearingEvent();
        if (!is_null($event))
        {
          // If enventId related to the concluded lisense is not in current jobs
          // event list, then the conclusion already exist and new evenst are discarded
          $eventId = $event->getEventId();
          if (array_search($eventId, $eventsFromJob) == false)
          {
            return false;
          }
        }
      }
    }

    // In some test cases variables can be null which make test cases to fail
    if (is_null($detectedLicenses) || is_null($removedLicenses) || count($detectedLicenses) == 0)
    {
      return false;
    }
    if ($detectedLicenses[0]->getLicenseId() == 507)
    {
      return false;
    }

    return true;
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $userId
   */
  protected function processClearingEventsForItem(ItemTreeBounds $itemTreeBounds, $userId, $groupId, $additionalEventsFromThisJob)
  {
    $this->dbManager->begin();

    $itemId = $itemTreeBounds->getItemId();

    switch ($this->conflictStrategyId)
    {
      case self::FORCE_DECISION:
        $createDecision = true;
        break;

      default:
        $createDecision = !$this->clearingDecisionProcessor->hasUnhandledScannerDetectedLicenses($itemTreeBounds, $groupId, $additionalEventsFromThisJob, $this->licenseMap);
    }
    if ($createDecision)
    {
      if ($this->isClearingAllowed($itemTreeBounds, $userId, $groupId, $additionalEventsFromThisJob))
      {
        if ( $this->clearingDao->isClearingEventRemove(current($additionalEventsFromThisJob)) )
        {
          foreach ($additionalEventsFromThisJob as $eventId)
          {
            $this->clearingDao->copyEventIdTo($eventId, $itemId, $userId, $groupId);
          }
          $this->clearingDao->markDecisionAsWip($itemId, $userId, $groupId);
        }
        else
        {
          $this->clearingDecisionProcessor->makeDecisionFromLastEvents($itemTreeBounds, $userId, $groupId,  DecisionTypes::IDENTIFIED,
              $this->decisionIsGlobal, $additionalEventsFromThisJob);
        }
      }
    }
    else
    {
      foreach ($additionalEventsFromThisJob as $eventId)
      {
        $this->clearingDao->copyEventIdTo($eventId, $itemId, $userId, $groupId);
      }
      $this->clearingDao->markDecisionAsWip($itemId, $userId, $groupId);
    }
    $this->heartbeat(1);

    $this->dbManager->commit();
  }
}
