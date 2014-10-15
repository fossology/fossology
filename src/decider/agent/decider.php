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
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\LicenseDecision\ClearingDecisionTypes;
use Fossology\Lib\Data\LicenseDecision\LicenseDecision;
use Fossology\Lib\Data\LicenseDecision\LicenseDecisionEvent;
use Fossology\Lib\Data\LicenseDecision\LicenseDecisionResult;

define("CLEARING_DECISION_IS_GLOBAL", false);

include_once(__DIR__ . "/version.php");

class DeciderAgent extends Agent
{
  /** @var int */
  private $conflictStrategyId;
  /** @var UploadDao */
  private $uploadDao;
  /** @var ClearingDecisionEventProcessor */
  private $clearingDecisionEventProcessor;
  /** @var ClearingDao */
  private $clearingDao;

  private $decisionIsGlobal = CLEARING_DECISION_IS_GLOBAL;

  /** @var ClearingDecisionTypes */
  private $clearingDecisionTypes;

  function __construct()
  {
    parent::__construct(AGENT_NAME, AGENT_VERSION, AGENT_REV);

    $args = getopt("k:", array(""));
    $this->conflictStrategyId = @$args['k'];

    global $container;
    $this->uploadDao = $container->get('dao.upload');

    $this->clearingDao = $container->get('dao.clearing');
    $this->clearingDecisionTypes = $container->get('clearing.decision.types');
    $this->clearingDecisionEventProcessor = $container->get('businessrules.clearing_decision_event_processor');

  }

  static protected function hasNewerUserEvents($events, $date)
  {
    foreach ($events as $licenseShortName => $licenseDecisionResult)
    {
      /** @var LicenseDecisionResult $licenseDecisionResult */
      $eventDate = $licenseDecisionResult->getDateTime();
      if ((($date === null) || ($eventDate > $date)))
        if (($licenseDecisionResult->hasAgentDecisionEvent()) && !($licenseDecisionResult->hasLicenseDecisionEvent()))
          return false;
    }

    return true;
  }

  protected function getDateOfLastRelevantClearing($userId, $uploadTreeId)
  {
    $lastDecision = $this->clearingDao->getRelevantClearingDecision($userId, $uploadTreeId);
    if (array_key_exists('date_added', $lastDecision))
      $lastDecisionDate = $lastDecision['date_added'];
    else
      $lastDecisionDate = null;
    return $lastDecisionDate;
  }


  /* true if small is a subset of big */
  static protected function array_contains($big, $small)
  {
    return count(array_diff($small, $big)) == 0;
  }

  function processLicenseDecisionEventOfCurrentJob()
  {
    $userId = $this->userId;
    $jobId = $this->jobId;

    $changedItems = $this->clearingDao->getItemsChangedBy($jobId);
    foreach ($changedItems as $uploadTreeId)
    {
      $this->dbManager->begin();  /* start transaction */

      $itemTreeBounds = $this->uploadDao->getFileTreeBounds($uploadTreeId);

      $lastDecisionDate = $this->getDateOfLastRelevantClearing($userId, $uploadTreeId);

    
      list($added, $removed) = $this->clearingDecisionEventProcessor->getCurrentLicenseDecisions($itemTreeBounds, $userId);

      if ($lastDecisionDate !== null)
      {
        $filter_since_event = function (LicenseDecisionEvent $event) use ($lastDecisionDate)
        {
          return $event->getDateTime() >= $lastDecisionDate;
        };
        $added = array_filter($added, $filter_since_event);
      }

      $canAutoDecide = true;
      foreach ($added as $event) {
        /** @var LicenseDecision $event */
        if ($event->getEventType() === LicenseDecisionResult::AGENT_DECISION_TYPE) {
          $canAutoDecide = false;
          break;
        }
      }

      if ($canAutoDecide)
      {
        $this->clearingDecisionEventProcessor->makeDecisionFromLastEvents($itemTreeBounds, $userId, ClearingDecisionTypes::IDENTIFIED, $this->decisionIsGlobal);
        $this->heartbeat(1);
      } else
      {
        //TODO implement conflict resolving strategies
        $this->heartbeat(0);
      }

      $this->dbManager->commit();  /* end transaction */
    }
  }


  function processUploadId($uploadId)
  {
    $this->processLicenseDecisionEventOfCurrentJob();

    return true;
  }
}

$agent = new DeciderAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->bail(0);