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
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseDecision\LicenseDecisionResult;

define("CLEARING_DECISION_IS_GLOBAL", false);

include_once(__DIR__ . "/version.php");

class DeciderAgent extends Agent
{
  const FORCE_DECISION = 1;
  /** @var int */
  private $conflictStrategyId;
  /** @var UploadDao */
  private $uploadDao;
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
    $this->conflictStrategyId = array_key_exists('k',$args) ? $args['k'] : NULL;

    global $container;
    $this->uploadDao = $container->get('dao.upload');

    $this->clearingDao = $container->get('dao.clearing');
    $this->decisionTypes = $container->get('decision.types');
    $this->clearingDecisionEventProcessor = $container->get('businessrules.clearing_decision_event_processor');

  }

  static protected function hasNewerUserEvents($events, $date)
  {
    foreach ($events as $licenseDecisionResult)
    {
      /** @var LicenseDecisionResult $licenseDecisionResult */
      $eventDate = $licenseDecisionResult->getDateTime();
      if ((($date === null) || ($eventDate > $date)) && ($licenseDecisionResult->hasAgentDecisionEvent()) && !($licenseDecisionResult->hasLicenseDecisionEvent()))
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

  function processLicenseDecisionEventOfCurrentJob()
  {
    $userId = $this->userId;
    $jobId = $this->jobId;

    $changedItems = $this->clearingDao->getItemsChangedBy($jobId);
    foreach ($changedItems as $uploadTreeId)
    {
      $this->processLicenseDecisionEventsForItem($uploadTreeId, $userId);
    }
  }


  function processUploadId($uploadId)
  {
    $this->processLicenseDecisionEventOfCurrentJob();

    return true;
  }

  /**
   * @param $uploadTreeId
   * @param $userId
   */
  protected function processLicenseDecisionEventsForItem($uploadTreeId, $userId)
  {
    $this->dbManager->begin();  /* start transaction */

    $itemTreeBounds = $this->uploadDao->getFileTreeBounds($uploadTreeId);

    $lastDecisionDate = $this->getDateOfLastRelevantClearing($userId, $uploadTreeId);

    list($added, $removed) = $this->clearingDecisionEventProcessor->filterRelevantLicenseDecisionEvents($userId, $itemTreeBounds, $lastDecisionDate);

    switch($this->conflictStrategyId){
      case DeciderAgent::FORCE_DECISION:
        $canAutoDecide = true;
        break;
      default:
        $canAutoDecide = $this->clearingDecisionEventProcessor->checkIfAutomaticDecisionCanBeMade($added, $removed);
    }

    if ($canAutoDecide)
    {
      $this->clearingDecisionEventProcessor->makeDecisionFromLastEvents($itemTreeBounds, $userId, DecisionTypes::IDENTIFIED, $this->decisionIsGlobal);
      $this->heartbeat(1);
    } else
    {
      $this->heartbeat(0);
    }

    $this->dbManager->commit();  /* end transaction */
  }
}

$agent = new DeciderAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->bail(0);