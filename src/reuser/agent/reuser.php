<?php
/*
 Copyright (C) 2014, Siemens AG
 Author: Daniele Fognini, Andreas Würl

 This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */


use Fossology\Lib\Agent\Agent;
use Fossology\Lib\BusinessRules\AgentLicenseEventProcessor;
use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\BusinessRules\ClearingEventProcessor;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\Clearing\ClearingResult;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\Item;
use Fossology\Lib\Util\ArrayOperation;

include_once(__DIR__ . "/version.php");

class ReuserAgent extends Agent
{

  /** @var UploadDao */
  private $uploadDao;

  /** @var ClearingEventProcessor */
  private $clearingEventProcessor;

  /** @var AgentLicenseEventProcessor */
  private $agentLicenseEventProcessor;

  /** @var ClearingDecisionFilter */
  private $clearingDecisionFilter;

  /** @var ClearingDecisionProcessor */
  private $clearingDecisionProcessor;

  /** @var ClearingDao */
  private $clearingDao;

  /** @var DecisionTypes */
  private $decisionTypes;

  function __construct()
  {
    parent::__construct(REUSER_AGENT_NAME, AGENT_VERSION, AGENT_REV);
    $this->uploadDao = $this->container->get('dao.upload');
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->decisionTypes = $this->container->get('decision.types');
    $this->clearingEventProcessor = $this->container->get('businessrules.clearing_event_processor');
    $this->clearingDecisionFilter = $this->container->get('businessrules.clearing_decision_filter');
    $this->clearingDecisionProcessor = $this->container->get('businessrules.clearing_decision_processor');
    $this->agentLicenseEventProcessor = $this->container->get('businessrules.agent_license_event_processor');
  }


  function processUploadId($uploadId)
  {
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $reusedUploadId = $this->uploadDao->getReusedUpload($uploadId);
    $itemTreeBoundsReused = $this->uploadDao->getParentItemBounds($reusedUploadId);
    if ($itemTreeBoundsReused)
    {
      $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBoundsReused);
      $filteredClearingDecisions = $this->clearingDecisionFilter->filterCurrentReusableClearingDecisions($clearingDecisions);
      $clearingDecisionsToImport = array_diff($clearingDecisions, $filteredClearingDecisions);
    } else
    {
      $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds);
      $clearingDecisions = $this->clearingDecisionFilter->filterCurrentReusableClearingDecisions($clearingDecisions);
      $clearingDecisionsToImport = array();
    }

    $clearingDecisionByFileId = $this->mapByFileId($clearingDecisions);
    $clearingDecisionToImportByFileId = $this->mapByFileId($clearingDecisionsToImport);

    /** @var Item[] $containedItems */
    $containedItems = ArrayOperation::callChunked(
        function ($fileIds) use ($itemTreeBounds)
        {
          return $this->uploadDao->getContainedItems(
              $itemTreeBounds,
              "pfile_fk = ANY($1)",
              array('{' . implode(', ', $fileIds) . '}')
          );
        }, array_keys($clearingDecisionByFileId), 100);

    foreach ($containedItems as $item)
    {
      $row = array('item' => $item);

      /** @var ClearingDecision $clearingDecision */
      $clearingDecision = $clearingDecisionByFileId[$item->getFileId()];

      $this->insertHistoricalClearingEvent($clearingDecision, $item);
      $fileId = $item->getFileId();
      if (array_key_exists($fileId, $clearingDecisionToImportByFileId))
      {
        $this->createCopyOfClearingDecision($item->getId(), $clearingDecisionToImportByFileId[$fileId]);
      }

      $this->heartbeat(1);
    }

    return true;
  }

  /**
   * @param ClearingDecision $clearingDecision
   * @param Item $item
   * @param LicenseRef $license
   * @param boolean $remove
   */
  protected
  function insertHistoricalClearingEvent(ClearingDecision $clearingDecision, Item $item)
  {
    $dateTime = $clearingDecision->getDateAdded();
    $dateTime->sub(new DateInterval('PT1S'));
    $itemId = $item->getId();
    foreach(array_merge($clearingDecision->getPositiveLicenses(), $clearingDecision->getNegativeLicenses()) as $clearingLicense) {
      $this->clearingDao->insertHistoricalClearingEvent(
        $dateTime,
        $itemId,
        $this->userId,
        $this->jobId,
        $clearingLicense->getLicenseId(),
        $clearingLicense->getType() | ClearingEventTypes::REUSED_BIT,
        $clearingLicense->isRemoved(),
        $clearingLicense->getReportInfo(),
        $clearingLicense->getComment()
      );
    }
  }

  /**
   * @param ClearingDecision[] $clearingDecisions
   * @return ClearingDecision[]
   */
  protected function mapByFileId($clearingDecisions)
  {
    $clearingDecisionByFileId = array();
    foreach ($clearingDecisions as $clearingDecision)
    {
      $fileId = $clearingDecision->getPfileId();
      if (!array_key_exists($fileId, $clearingDecisionByFileId)) {
        $clearingDecisionByFileId[$fileId] = $clearingDecision;
      }
    }
    return $clearingDecisionByFileId;
  }

  /**
   * @param int $itemId
   * @param ClearingDecision $clearingDecisionToCopy
   */
  protected function createCopyOfClearingDecision($itemId, $clearingDecisionToCopy)
  {
    $this->clearingDao->insertClearingDecision(
        $itemId,
        $this->userId,
        $clearingDecisionToCopy->getType(),
        $clearingDecisionToCopy->getScope(),
        $clearingDecisionToCopy->getPositiveLicenses(),
        $clearingDecisionToCopy->getNegativeLicenses()
    );
  }
}

$agent = new ReuserAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);