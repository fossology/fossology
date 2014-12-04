<?php
/*
 Copyright (C) 2014, Siemens AG
 Author: Daniele Fognini, Andreas Würl

 This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Fossology\Reuser;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\BusinessRules\AgentLicenseEventProcessor;
use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\BusinessRules\ClearingEventProcessor;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;
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
    /* TODO here it feels like we need a transaction
     * but it also feels like it would have too big a scope
     */

    $groupId = $this->groupId;
    $userId = $this->userId;

    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $reusedUploadId = $this->uploadDao->getReusedUpload($uploadId);
    $itemTreeBoundsReused = $this->uploadDao->getParentItemBounds($reusedUploadId);

    if (false === $itemTreeBoundsReused)
    {
      return true;
    }

    $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBoundsReused, $groupId);
    $currenlyVisibleClearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $groupId);

    $currenlyVisibleClearingDecisionsById = $this->mapByClearingId($currenlyVisibleClearingDecisions);
    $clearingDecisionsById = $this->mapByClearingId($clearingDecisions);

    $clearingDecisionsToImport = array_diff_key($clearingDecisionsById,$currenlyVisibleClearingDecisionsById);

    $clearingDecisionToImportByFileId = $this->mapByFileId($clearingDecisionsToImport);

    $uploadDao = $this->uploadDao;
    /** @var Item[] $containedItems */
    $containedItems = ArrayOperation::callChunked(
        function ($fileIds) use ($itemTreeBounds, $uploadDao)
        {
          return $uploadDao->getContainedItems(
              $itemTreeBounds,
              "pfile_fk = ANY($1)",
              array('{' . implode(', ', $fileIds) . '}')
          );
        }, array_keys($clearingDecisionToImportByFileId), 100);

    foreach ($containedItems as $item)
    {
      $fileId = $item->getFileId();
      if (array_key_exists($fileId, $clearingDecisionToImportByFileId))
      {
        $this->createCopyOfClearingDecision($item->getId(), $userId, $groupId, $clearingDecisionToImportByFileId[$fileId]);
      }
      else
      {
        throw new \Exception("bad internal state");
      }

      $this->heartbeat(1);
    }

    return true;
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
  protected function createCopyOfClearingDecision($itemId, $userId, $groupId, $clearingDecisionToCopy)
  {
    $clearingEventIdsToCopy = array();
    /** @var ClearingEvent $clearingEvent */
    foreach ($clearingDecisionToCopy->getClearingEvents() as $clearingEvent)
    {
      $clearingEventIdsToCopy[] = $clearingEvent->getEventId();
    }

    $this->clearingDao->createDecisionFromEvents(
        $itemId,
        $userId,
        $groupId,
        $clearingDecisionToCopy->getType(),
        $clearingDecisionToCopy->getScope(),
        $clearingEventIdsToCopy
    );
  }

  /** @parm ClearingDecision[] $clearingDecisions */
  public function mapByClearingId($clearingDecisions)
  {
    $mapped = array();

    foreach ($clearingDecisions as $clearingDecision) {
      $mapped[$clearingDecision->getClearingId()] = $clearingDecision;
    }

    return $mapped;
  }

}