<?php
/*
 Author: Daniele Fognini
 Copyright (C) 2014, Siemens AG

 This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

define("AGENT_NAME", "decider");

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\BusinessRules\ClearingDecisionEventProcessor;
use Fossology\Lib\BusinessRules\ClearingEventProcessor;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Clearing\ClearingResult;

define("CLEARING_DECISION_IS_GLOBAL", false);

include_once(__DIR__ . "/version.php");

class DeciderAgent extends Agent
{
  const FORCE_DECISION = 1;
  /** @var int */
  private $conflictStrategyId;
  /** @var UploadDao */
  private $uploadDao;
  /** @var ClearingEventProcessor */
  private $clearingEventProcessor;
  /** @var ClearingDecisionEventProcessor */
  private $clearingDecisionEventProcessor;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var int */
  private $decisionIsGlobal = CLEARING_DECISION_IS_GLOBAL;
  /** @var DecisionTypes */
  private $decisionTypes;

  function __construct()
  {
    parent::__construct(AGENT_NAME, AGENT_VERSION, AGENT_REV);

    $args = getopt("k:", array(""));
    $this->conflictStrategyId = array_key_exists('k', $args) ? $args['k'] : NULL;

    $this->uploadDao = $this->container->get('dao.upload');

    $this->clearingDao = $this->container->get('dao.clearing');
    $this->decisionTypes = $this->container->get('decision.types');
    $this->clearingEventProcessor = $this->container->get('businessrules.clearing_event_processor');
    $this->clearingDecisionEventProcessor = $this->container->get('businessrules.clearing_decision_event_processor');
  }

  static protected function hasNewerUserEvents($events, $date)
  {
    foreach ($events as $licenseDecisionResult)
    {
      /** @var ClearingResult $licenseDecisionResult */
      $eventDate = $licenseDecisionResult->getDateTime();
      if ((($date === null) || ($eventDate > $date)) && $licenseDecisionResult->hasAgentDecisionEvent() && !$licenseDecisionResult->hasClearingEvent())
      {
        return false;
      }
    }
    return true;
  }

  protected function getDateOfLastRelevantClearing($userId, $uploadTreeId)
  {
    $lastDecision = $this->clearingDao->getRelevantClearingDecision($userId, $uploadTreeId);
    return $lastDecision !== null ? $lastDecision->getDateAdded() : null;
  }


  /* true if small is a subset of big */
  static protected function array_contains($big, $small)
  {
    return count(array_diff($small, $big)) == 0;
  }

  function processClearingEventOfCurrentJob()
  {
    $userId = $this->userId;
    $jobId = $this->jobId;

    $changedItems = $this->clearingDao->getItemsChangedBy($jobId);
    foreach ($changedItems as $uploadTreeId)
    {
      $this->processClearingEventsForItem($uploadTreeId, $userId);
    }
  }


  function processUploadId($uploadId)
  {
    $this->processClearingEventOfCurrentJob();

    return true;
  }

  /**
   * @param $uploadTreeId
   * @param $userId
   */
  protected function processClearingEventsForItem($uploadTreeId, $userId)
  {
    $this->dbManager->begin();  /* start transaction */

    $itemTreeBounds = $this->uploadDao->getFileTreeBounds($uploadTreeId);

    $lastDecision = $this->getDateOfLastRelevantClearing($userId, $uploadTreeId);

    $orderedEvents = $this->clearingDao->getRelevantClearingEvents($userId, $uploadTreeId);
    $currentEvents = $this->clearingEventProcessor->filterEventsByTime($orderedEvents, $lastDecision);
    $unionedEvents = $this->clearingEventProcessor->filterEffectiveEvents($currentEvents);

    switch ($this->conflictStrategyId)
    {
      case DeciderAgent::FORCE_DECISION:
        $canAutoDecide = true;
        break;

      default:
        $canAutoDecide = $this->clearingDecisionEventProcessor->checkIfAutomaticDecisionCanBeMade($unionedEvents);
    }

    if ($canAutoDecide)
    {
      $this->clearingDecisionEventProcessor->makeDecisionFromLastEvents($itemTreeBounds, $userId, DecisionTypes::IDENTIFIED, $this->decisionIsGlobal);
    }
    else
    {
      $this->clearingDao->markDecisionAsWip($uploadTreeId, $userId);
    }
    $this->heartbeat(1);
    
    $this->dbManager->commit();  /* end transaction */
  }
}

$agent = new DeciderAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->bail(0);