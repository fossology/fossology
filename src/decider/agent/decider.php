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
use Fossology\Lib\Dao\Data\LicenseDecision\LicenseDecisionEvent;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\ClearingDecision;

define("CLEARING_DECISION_TYPE", ClearingDecision::IDENTIFIED);
define("CLEARING_DECISION_IS_GLOBAL", false);

include_once(__DIR__."/version.php");

class DeciderAgent extends Agent
{
  private $uploadTreeId;
  /** @var UploadDao */
  private $uploadDao;
  /** @var ClearingDecisionEventProcessor */
  private $clearingDecisionEventProcessor;
   /** @var ClearingDao */
  private $clearingDao;

  private $decisionIsGlobal = CLEARING_DECISION_IS_GLOBAL;
  private $decisionType;

  function __construct()
  {
    parent::__construct(AGENT_NAME, AGENT_VERSION, AGENT_REV);

    $args = getopt("U:",array(""));
    $this->uploadTreeId = @$args['U'];

    global $container;
    $this->uploadDao = $container->get('dao.upload');

    $this->clearingDao = $container->get('dao.clearing');
    $this->clearingDecisionEventProcessor = $container->get('businessrules.clearing_decision_event_processor');

    $this->initializeDecisionType();
  }

  private function initializeDecisionType() {
    $row = $this->dbManager->getSingleRow("SELECT type_pk FROM clearing_decision_type WHERE meaning = $1", array(CLEARING_DECISION_TYPE));
    if ($row === false)
    {
      print "ERROR: could not initialize clearing type for ". CLEARING_DECISION_TYPE;
      $this->bail(6);
    }
    else
    {
      $this->decisionType = $row['type_pk'];
    }
  }

  static protected function hasOnlyNewerEvents($events, $date)
  {
    foreach($events as $licenseShortName => $licenseDecisionEvent)
    {
      /** @var LicenseDecisionEvent $licenseDecisionEvent */
      $eventDate = $licenseDecisionEvent->getEpoch();
      if ((($date !== null)&&($eventDate > $date)))
        return false;
    }

    return true;
  }

  protected function getDateOfLastRelevantClearing($userId, $uploadTreeId){
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
    return count(array_diff($small,$big)) == 0;
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

      $canAutoDecide = true;
      list($added, $removed) = $this->clearingDecisionEventProcessor->getCurrentLicenseDecisions($userId, $uploadTreeId);

      /* TODO @1
       *
       * a concurrent bulk scan could have created an event
       * and its decider could have not yet arrived here
       *
       * we get two events which should be auto-decided,
       * but neither decider has an opportunity
       *
       * what do we do?
       */
      $canAutoDecide &= DeciderAgent::hasOnlyNewerEvents($added, $lastDecisionDate);
      $canAutoDecide &= DeciderAgent::hasOnlyNewerEvents($removed, $lastDecisionDate);

      if (($canAutoDecide) && ($lastDecisionDate === null))
      {
        /* if there was no previous decision
         * we check that the events from this job (TODO @1 ?)
         * at least confirm or remove all the licenses found by the scanners
         */

        $changedLicenses = array_merge(array_keys($added),array_keys($removed));

        $agentDetectedLicenses = array_keys($this->clearingDecisionEventProcessor->getAgentDetectedLicenses($itemTreeBounds));

        $canAutoDecide = (DeciderAgent::array_contains($changedLicenses, $agentDetectedLicenses));
      }

      if ($canAutoDecide)
      {
        $this->clearingDecisionEventProcessor->makeDecisionFromLastEvents($itemTreeBounds, $userId, $this->decisionType, $this->decisionIsGlobal);
        $this->heartbeat(1);
      }
      else
      {
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