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

namespace Fossology\Decider;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;

define("CLEARING_DECISION_IS_GLOBAL", false);

include_once(__DIR__ . "/version.php");

class DeciderAgent extends Agent
{
  const FORCE_DECISION = 1;

  /** @var int */
  private $conflictStrategyId;
  /** @var UploadDao */
  private $uploadDao;
  /** @var ClearingDecisionProcessor */
  private $clearingDecisionProcessor;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var int */
  private $decisionIsGlobal = CLEARING_DECISION_IS_GLOBAL;
  /** @var DecisionTypes */
  private $decisionTypes;
  /** @var LicenseMap */
  private $licenseMap;
  
  function __construct()
  {
    parent::__construct(AGENT_NAME, AGENT_VERSION, AGENT_REV);

    $args = getopt($this->schedulerHandledOpts."k:", $this->schedulerHandledLongOpts);
    $this->conflictStrategyId = array_key_exists('k', $args) ? $args['k'] : NULL;

    $this->uploadDao = $this->container->get('dao.upload');
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->decisionTypes = $this->container->get('decision.types');
    $this->clearingDecisionProcessor = $this->container->get('businessrules.clearing_decision_processor');
    $this->licenseMap = new LicenseMap($this->dbManager, $this->groupId, LicenseMap::CONCLUSION);
  }

  function processClearingEventOfCurrentJob()
  {
    $userId = $this->userId;
    $groupId = $this->groupId;
    $jobId = $this->jobId;

    $eventsOfThisJob = $this->clearingDao->getEventIdsOfJob($jobId);
    foreach ($eventsOfThisJob as $uploadTreeId => $additionalEventsFromThisJob)
    {
      foreach($this->loopContainedItems($uploadTreeId) as $itemTreeBounds)
      {
        $this->processClearingEventsForItem($itemTreeBounds, $userId, $groupId, $additionalEventsFromThisJob);
      }
    }
  }

  private function loopContainedItems($uploadTreeId)
  {
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($uploadTreeId);
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
    $this->processClearingEventOfCurrentJob();

    return true;
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $userId
   */
  protected function processClearingEventsForItem(ItemTreeBounds $itemTreeBounds, $userId, $groupId, $additionalEventsFromThisJob)
  {
    $this->dbManager->begin();  /* start transaction */

    $itemId = $itemTreeBounds->getItemId();

    switch ($this->conflictStrategyId)
    {
      case DeciderAgent::FORCE_DECISION:
        $createDecision = true;
        break;

      default:
        $createDecision = !$this->clearingDecisionProcessor->hasUnhandledScannerDetectedLicenses($itemTreeBounds, $groupId, $additionalEventsFromThisJob);
    }

    if ($createDecision)
    {
      $this->clearingDecisionProcessor->makeDecisionFromLastEvents($itemTreeBounds, $userId, $groupId, DecisionTypes::IDENTIFIED, $this->decisionIsGlobal, $additionalEventsFromThisJob);
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

    $this->dbManager->commit();  /* end transaction */
  }
}