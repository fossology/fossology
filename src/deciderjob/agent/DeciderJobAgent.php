<?php
/*
 Author: Daniele Fognini
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file DeciderJobAgent.php
 * @brief Decider agent
 * @page deciderjob Decider Job
 * @tableofcontents
 * @section deciderjobabout About decider job
 * The agent apply the decisions found by Monk Bulk run.
 *
 * -# Get the clearing events for the given upload.
 * -# Get every item for the upload
 *   -# If there are new events or force run
 *     -# Create decisions based on events
 *   -# Otherwise copy events and mark them as WIP
 *
 * @note DeciderJobAgent (`agent_deciderjob`) must be added as a dependency to
 * the monk bulk while scheduling the `agent_monk_bulk job`
 * @section deciderjobsource Agent source
 *   - @link src/deciderjob/agent @endlink
 *   - @link src/deciderjob/ui @endlink
 *   - Functional test cases @link src/deciderjob/agent_tests/Functional @endlink
 */
/**
 * @namespace Fossology::DeciderJob
 * @brief Namespace of DeciderJob agent
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

define("CLEARING_DECISION_IS_GLOBAL", false);

include_once(__DIR__ . "/version.php");

/**
 * @class DeciderJobAgent
 * @brief Get the decision from Monk bulk and apply decisions
 */
class DeciderJobAgent extends Agent
{
  const FORCE_DECISION = 1;

  /** @var int $conflictStrategyId
   * Conflict resolution strategy to be used (0=>unhandled events,1=>force)
   */
  private $conflictStrategyId;
  /** @var UploadDao $uploadDao
   * UploadDao object
   */
  private $uploadDao;
  /** @var ClearingDecisionProcessor $clearingDecisionProcessor
   * ClearingDecisionProcessor to be used
   */
  private $clearingDecisionProcessor;
  /** @var AgentLicenseEventProcessor $agentLicenseEventProcessor
   * AgentLicenseEventProcessor to be used
   */
  private $agentLicenseEventProcessor;
  /** @var ClearingDao $clearingDao
   * ClearingDao object
   */
  private $clearingDao;
  /** @var HighlightDao $highlightDao
   * HighlightDao object
   */
  private $highlightDao;
  /** @var boolean $decisionIsGlobal
   * If the decision is global
   */
  private $decisionIsGlobal = CLEARING_DECISION_IS_GLOBAL;
  /** @var DecisionTypes $decisionTypes
   * DecisionTypes object
   */
  private $decisionTypes;
  /** @var LicenseMap $licenseMap
   * LicenseMap object
   */
  private $licenseMap = null;
  /** @var int $licenseMapUsage
   * @see LicenseMap
   */
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

    $this->agentSpecifOptions = "k:";
    $this->licenseMapUsage = $licenseMapUsage;
  }

  /**
   * @brief Process clearing events of current job handled by agent
   */
  function processClearingEventOfCurrentJob()
  {
    $userId = $this->userId;
    $groupId = $this->groupId;
    $jobId = $this->jobId;

    $eventsOfThisJob = $this->clearingDao->getEventIdsOfJob($jobId);
    foreach ($eventsOfThisJob as $uploadTreeId => $additionalEventsFromThisJob) {
      $containerBounds = $this->uploadDao->getItemTreeBounds($uploadTreeId);
      foreach ($this->loopContainedItems($containerBounds) as $itemTreeBounds) {
        $this->processClearingEventsForItem($itemTreeBounds, $userId, $groupId, $additionalEventsFromThisJob);
      }
    }
  }

  /**
   * @brief Get items contained inside an item tree
   * @param ItemTreeBounds $itemTreeBounds Item tree to be looped
   * @return ItemTreeBounds Array of items inside given item tree
   */
  private function loopContainedItems($itemTreeBounds)
  {
    if (!$itemTreeBounds->containsFiles()) {
      return array($itemTreeBounds);
    }
    $result = array();
    $condition = "(ut.lft BETWEEN $1 AND $2) AND ((ut.ufile_mode & (3<<28)) = 0)";
    $params = array($itemTreeBounds->getLeft(), $itemTreeBounds->getRight());
    foreach ($this->uploadDao->getContainedItems($itemTreeBounds, $condition, $params) as $item) {
      $result[] = $item->getItemTreeBounds();
    }
    return $result;
  }

  /**
   * @copydoc Fossology::Lib::Agent::Agent::processUploadId()
   * @see Fossology::Lib::Agent::Agent::processUploadId()
   */
  function processUploadId($uploadId)
  {
    $args = $this->args;
    $this->conflictStrategyId = array_key_exists('k', $args) ? $args['k'] : null;

    $this->licenseMap = new LicenseMap($this->dbManager, $this->groupId, $this->licenseMapUsage);

    if ($this->conflictStrategyId == 'global') {
      $uploadTreeId = 0; // zero because we are checking candidate license for whole upload.
      if (!empty($this->clearingDao->getCandidateLicenseCountForCurrentDecisions($uploadTreeId, $uploadId))) {
        throw new \Exception( _("Cannot add candidate license as global decision\n") );
      }
      $this->heartbeat(1);
      $this->heartbeat($this->clearingDao->marklocalDecisionsAsGlobal($uploadId));
    } else {
      $this->processClearingEventOfCurrentJob();
    }
    return true;
  }

  /**
   * @brief Get an item, process events and create new decisions
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $userId
   * @param int $groupId
   * @param array $additionalEventsFromThisJob
   */
  protected function processClearingEventsForItem(ItemTreeBounds $itemTreeBounds, $userId, $groupId, $additionalEventsFromThisJob)
  {
    $this->dbManager->begin();

    $itemId = $itemTreeBounds->getItemId();

    switch ($this->conflictStrategyId) {
      case self::FORCE_DECISION:
        $createDecision = true;
        break;

      default:
        $createDecision = !$this->clearingDecisionProcessor->hasUnhandledScannerDetectedLicenses($itemTreeBounds, $groupId, $additionalEventsFromThisJob, $this->licenseMap);
    }

    if ($createDecision) {
      $this->clearingDecisionProcessor->makeDecisionFromLastEvents($itemTreeBounds, $userId, $groupId, DecisionTypes::IDENTIFIED, $this->decisionIsGlobal, $additionalEventsFromThisJob);
    } else {
      foreach ($additionalEventsFromThisJob as $eventId) {
        $this->clearingDao->copyEventIdTo($eventId, $itemId, $userId, $groupId);
      }
      $this->clearingDao->markDecisionAsWip($itemId, $userId, $groupId);
    }
    $this->heartbeat(1);

    $this->dbManager->commit();
  }
}
