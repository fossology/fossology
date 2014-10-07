<?php
/*
 Author: Daniele Fognini
 Copyright (C) 2014, Siemens AG

 This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

define("AGENT_NAME", "decider");

use Fossology\Lib\Data\ClearingDecision;
define("CLEARING_DECISION_TYPE", ClearingDecision::IDENTIFIED);
define("CLEARING_DECISION_IS_GLOBAL", false);

include_once(__DIR__."/version.php");

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\BusinessRules\ClearingDecisionEventProcessor;

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

  private function hasOnlyNewerEventsInJob($events, $date, $jobId)
  {
    foreach($events as $licName => $properties)
    {
      $eventDate = $properties['dateAdded'];
      if (($eventDate > $date) && ($properties['jobId'] !== $jobId))
        return false;
    }

    return true;
  }

  private function getDateOfLastRelevantClearing($userId, $uploadTreeId){
    $lastDecision = $this->clearingDao->getRelevantClearingDecision($userId, $uploadTreeId);
    if (array_key_exists('date_added', $lastDecision))
      $lastDecisionDate = $lastDecision['date_added'];
    else
      $lastDecisionDate = 0;
    return $lastDecisionDate;
  }

  function processUploadId($uploadId)
  {
    $jobId = $this->jobId;
    $userId = $this->userId;

    $changedItems = $this->clearingDao->getItemsChangedBy($jobId);
    foreach ($changedItems as $uploadTreeId)
    {
      $itemTreeBounds = $this->uploadDao->getFileTreeBounds($uploadTreeId);

      $lastDecisionDate = $this->getDateOfLastRelevantClearing($userId, $uploadTreeId);

      list($added, $removed) = $this->clearingDao->getCurrentLicenseDecision($userId, $uploadTreeId);

      $canAutoDecide = true;
      $canAutoDecide &= $this->hasOnlyNewerEventsInJob($added, $lastDecisionDate, $jobId);
      $canAutoDecide &= $this->hasOnlyNewerEventsInJob($removed, $lastDecisionDate, $jobId);

      if ($canAutoDecide)
      {
        $this->clearingDecisionEventProcessor->makeDecisionFromLastEvents($itemTreeBounds, $this->userId, $this->decisionType, $this->decisionIsGlobal);
        $this->heartbeat(1);
      }
      else
      {
        $this->heartbeat(0);
      }
    }

    return true;
  }
}

$agent = new DeciderAgent();
$agent->scheduler_connect();
$agent->run_schedueler_event_loop();
$agent->bail(0);