<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG
Author: Daniele Fognini, Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Reuser;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\BusinessRules\AgentLicenseEventProcessor;
use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\BusinessRules\ClearingEventProcessor;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Tree\Item;
use Fossology\Lib\Util\ArrayOperation;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;

include_once(__DIR__ . "/version.php");

/**
 * @file
 * @brief Reuser agent source
 * @class ReuserAgent
 * @brief The reuser agent
 */
class ReuserAgent extends Agent
{
  /** @var UploadDao $uploadDao
   * UploadDao object
   */
  private $uploadDao;
  /** @var ClearingEventProcessor $clearingEventProcessor
   * ClearingEventProcessor object
   */
  private $clearingEventProcessor;
  /** @var AgentLicenseEventProcessor $agentLicenseEventProcessor
   * AgentLicenseEventProcessor object
   */
  private $agentLicenseEventProcessor;
  /** @var ClearingDecisionFilter $clearingDecisionFilter
   * ClearingDecisionFilter object
   */
  private $clearingDecisionFilter;
  /** @var ClearingDecisionProcessor $clearingDecisionProcessor
   * ClearingDecisionProcessor object
   */
  private $clearingDecisionProcessor;
  /** @var ClearingDao $clearingDao
   * ClearingDao object
   */
  private $clearingDao;
  /** @var CopyrightDao $copyrightDao
   * CopyrightDao object
   */
  private $copyrightDao;
  /** @var AgentDao $aagentDao
   * AgentDao object
   */
  private $aagentDao;
  /** @var DecisionTypes $decisionTypes
   * DecisionTypes object
   */
  private $decisionTypes;

  function __construct()
  {
    parent::__construct(REUSER_AGENT_NAME, AGENT_VERSION, AGENT_REV);
    $this->aagentDao = $this->container->get('dao.agent');
    $this->uploadDao = $this->container->get('dao.upload');
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->copyrightDao = $this->container->get('dao.copyright');
    $this->decisionTypes = $this->container->get('decision.types');
    $this->clearingEventProcessor = $this->container->get('businessrules.clearing_event_processor');
    $this->clearingDecisionFilter = $this->container->get('businessrules.clearing_decision_filter');
    $this->clearingDecisionProcessor = $this->container->get('businessrules.clearing_decision_processor');
    $this->agentLicenseEventProcessor = $this->container->get('businessrules.agent_license_event_processor');
  }

  /**
   * @brief Get the upload items and reuse based on reuse mode
   * @param int $uploadId Upload id to process
   * @see Fossology::Lib::Agent::Agent::processUploadId()
   */
  function processUploadId($uploadId)
  {
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId);
    foreach ($this->uploadDao->getReusedUpload($uploadId, $this->groupId) as $reuseTriple) {
      // Get the reuse upload id
      $reusedUploadId = $reuseTriple['reused_upload_fk'];
      // Get the group id
      $reusedGroupId = $reuseTriple['reused_group_fk'];
      // Get the reuse mode
      $reuseMode = $reuseTriple['reuse_mode'];
      // Get the ItemTreeBounds for the upload
      $itemTreeBoundsReused = $this->uploadDao->getParentItemBounds($reusedUploadId);
      if (false === $itemTreeBoundsReused) {
        continue;
      }

      if ($reuseMode & UploadDao::REUSE_ENHANCED) {
        $this->processEnhancedUploadReuse($itemTreeBounds, $itemTreeBoundsReused, $reusedGroupId);
      } else {
        $this->processUploadReuse($itemTreeBounds, $itemTreeBoundsReused, $reusedGroupId);
      }

      if ($reuseMode & UploadDao::REUSE_MAIN) {
        $this->reuseMainLicense($uploadId, $this->groupId, $reusedUploadId, $reusedGroupId);
      }

      if ($reuseMode & UploadDao::REUSE_CONF) {
        $this->reuseConfSettings($uploadId, $reusedUploadId);
      }

      if ($reuseMode & UploadDao::REUSE_COPYRIGHT) {
        $this->reuseCopyrights($uploadId, $reusedUploadId);
      }
    }
    return true;
  }
  protected function reuseConfSettings($uploadId, $reusedUploadId)
  {
    if (!$this->uploadDao->insertReportConfReuse($uploadId, $reusedUploadId)) {
      echo "INFO :: Report configuration for select upload doesn't exists. Unable to copy!!!";
    }
    $this->heartbeat(1);
    return true;
  }

  /**
   * @brief Reuse main license from previous upload
   *
   * Get add the main licenses from previous upload and make them in new upload
   * @param int $uploadId       Current upload
   * @param int $groupId        Current user
   * @param int $reusedUploadId Upload to reuse
   * @param int $reusedGroupId  Group of reused upload
   * @return boolean True once finished
   */
  protected function reuseMainLicense($uploadId, $groupId, $reusedUploadId, $reusedGroupId)
  {
    $mainLicenseIds = $this->clearingDao->getMainLicenseIds($reusedUploadId, $reusedGroupId);
    if (!empty($mainLicenseIds)) {
      foreach ($mainLicenseIds as $mainLicenseId) {
        if (in_array($mainLicenseId, $this->clearingDao->getMainLicenseIds($uploadId, $groupId))) {
          continue;
        } else {
          $this->clearingDao->makeMainLicense($uploadId, $groupId, $mainLicenseId);
        }
      }
    }
    return true;
  }

  /**
   * @brief getAgentId from upload
   *
   * Get add the main licenses from previous upload and make them in new upload
   * @param int $uploadId       uplaodId
   * @return int agentId
   */
  protected function getAgentId($uploadId)
  {
    $agentName = 'copyright';

    /** @var ScanJobProxy $scanJobProxy */
    $scanJobProxy = new ScanJobProxy($this->aagentDao, $uploadId);

    $scanJobProxy->createAgentStatus(array($agentName));
    $selectedScanners = $scanJobProxy->getLatestSuccessfulAgentIds();
    if (!array_key_exists($agentName, $selectedScanners)) {
      return;
    }
    return $selectedScanners[$agentName];
  }


  /**
   * @brief Reuse deactivated Copyrights from previous upload
   *
   * Get add the main licenses from previous upload and make them in new upload
   * @param int $uploadId       Current upload
   * @param int $reusedUploadId Upload to reuse
   * @return boolean True once finished
   */
  protected function reuseCopyrights($uploadId, $reusedUploadId)
  {
    $agentId = $this->getAgentId($uploadId);
    $reusedAgentId = $this->getAgentId($reusedUploadId);
    if ($agentId == null || $reusedAgentId == null) {
      return true;
    }
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $extrawhere = ' agent_fk='.$agentId;
    $allCopyrights = $this->copyrightDao->getScannerEntries('copyright',
      $uploadTreeTableName, $uploadId, null, $extrawhere);

    $reusedCopyrights = $this->copyrightDao->getAllEventEntriesForUpload(
      $reusedUploadId, $reusedAgentId);

    if (!empty($reusedCopyrights) && !empty($allCopyrights)) {
      foreach ($reusedCopyrights as $reusedCopyright) {
        foreach ($allCopyrights as $copyrightKey => $copyright) {
          if (strcmp($copyright['hash'], $reusedCopyright['hash']) == 0) {
            if ($this->dbManager->booleanFromDb($reusedCopyright['is_enabled'])) {
              $action = "update";
              $content = $reusedCopyright["contentedited"];
            } else {
              $action = "delete";
              $content = "";
            }
            $hash = $copyright['hash'];
            $item = $this->uploadDao->getItemTreeBounds(intval($copyright['uploadtree_pk']),
                      $uploadTreeTableName);
            $this->copyrightDao->updateTable($item, $hash, $content,
              $this->userId, 'copyright', $action);
            unset($allCopyrights[$copyrightKey]);
            $this->heartbeat(1);
          }
        }
      }
    }
    return true;
  }

  /**
   * @brief Compare the files from both uploads and copy decisions
   * @param ItemTreeBounds $itemTreeBounds        Current upload
   * @param ItemTreeBounds $itemTreeBoundsReused  Reused upload
   * @param int $reusedGroupId
   * @throws \Exception
   * @return boolean True once finished
   */
  protected function processUploadReuse($itemTreeBounds, $itemTreeBoundsReused, $reusedGroupId)
  {
    $groupId = $this->groupId;
    $userId = $this->userId;

    $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBoundsReused, $reusedGroupId);
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

    foreach ($containedItems as $item) {
      $fileId = $item->getFileId();
      if (empty($fileId)) {
        continue;
      }
      if (array_key_exists($fileId, $clearingDecisionToImportByFileId)) {
        $this->createCopyOfClearingDecision($item->getId(), $userId, $groupId,
          $clearingDecisionToImportByFileId[$fileId]);
      } else {
        throw new \Exception("bad internal state");
      }

      $this->heartbeat(1);
    }

    return true;
  }

  /**
   * @brief Get clearing decisions and use copyClearingDecisionIfDifferenceIsSmall()
   * @param ItemTreeBounds $itemTreeBounds        Current upload
   * @param ItemTreeBounds $itemTreeBoundsReused  Reused upload
   * @param int $reusedGroupId
   * @see copyClearingDecisionIfDifferenceIsSmall()
   */
  protected function processEnhancedUploadReuse($itemTreeBounds, $itemTreeBoundsReused, $reusedGroupId)
  {
    $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBoundsReused, $reusedGroupId);
    $currenlyVisibleClearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $this->groupId);

    $currenlyVisibleClearingDecisionsById = $this->mapByClearingId($currenlyVisibleClearingDecisions);
    $clearingDecisionsById = $this->mapByClearingId($clearingDecisions);

    $clearingDecisionsToImport = array_diff_key($clearingDecisionsById,$currenlyVisibleClearingDecisionsById);

    $sql = "SELECT ut.* FROM uploadtree ur, uploadtree ut WHERE ur.upload_fk=$2"
         . " AND ur.pfile_fk=$3 AND ut.upload_fk=$1 AND ut.ufile_name=ur.ufile_name";
    $stmt = __METHOD__.'.reuseByName';
    $this->dbManager->prepare($stmt, $sql);
    $treeDao = $this->container->get('dao.tree');

    foreach ($clearingDecisionsToImport as $clearingDecision) {
      $reusedPath = $treeDao->getRepoPathOfPfile($clearingDecision->getPfileId());
      if (empty($reusedPath)) {
        // File missing from repo
        continue;
      }

      $res = $this->dbManager->execute($stmt,array($itemTreeBounds->getUploadId(),
        $itemTreeBoundsReused->getUploadId(),$clearingDecision->getPfileId()));
      while ($row = $this->dbManager->fetchArray($res)) {
        $newPath = $treeDao->getRepoPathOfPfile($row['pfile_fk']);
        if (empty($newPath)) {
          // File missing from repo
          continue;
        }
        $this->copyClearingDecisionIfDifferenceIsSmall($reusedPath, $newPath, $clearingDecision, $row['uploadtree_pk']);
      }
      $this->dbManager->freeResult($res);
    }
  }

  /**
   * @brief Use `diff` tool to compare files
   *
   * Uses `diff` tool to compare two files. If the line difference is less
   * than 5, then reuser copy the decisions.
   * @param string $reusedPath
   * @param string $newPath
   * @param ClearingDecision $clearingDecision Array of clearing decisions
   * @param int $itemId
   * @throws \Exception Throws if `diff` tool fails
   */
  protected function copyClearingDecisionIfDifferenceIsSmall($reusedPath,$newPath,$clearingDecision,$itemId)
  {
    $diffLevel = exec("diff $reusedPath $newPath | wc -l");
    if ($diffLevel === false) {
      throw new \Exception('cannot use diff tool');
    }
    if ($diffLevel < 5) {
      $this->createCopyOfClearingDecision($itemId, $this->userId, $this->groupId, $clearingDecision);
      $this->heartbeat(1);
    }
  }

  /**
   * @brief Maps clearing decisions by file id
   *
   * Creates array with file ids as key and ClearingDecision object as value
   * @param ClearingDecision $clearingDecisions  Array of clearing decisions
   * @return ClearingDecision[]
   */
  protected function mapByFileId($clearingDecisions)
  {
    $clearingDecisionByFileId = array();
    foreach ($clearingDecisions as $clearingDecision) {
      $fileId = $clearingDecision->getPfileId();
      if (!array_key_exists($fileId, $clearingDecisionByFileId)) {
        $clearingDecisionByFileId[$fileId] = $clearingDecision;
      }
    }
    return $clearingDecisionByFileId;
  }

  /**
   * @brief Copy clearing decisions from an upload tree item
   * @param int $itemId
   * @param int $userId
   * @param int $groupId
   * @param ClearingDecision $clearingDecisionToCopy
   */
  protected function createCopyOfClearingDecision($itemId, $userId, $groupId, $clearingDecisionToCopy)
  {
    $clearingEventIdsToCopy = array();
    /** @var ClearingEvent $clearingEvent */
    foreach ($clearingDecisionToCopy->getClearingEvents() as $clearingEvent) {
      $licenseId = $clearingEvent->getLicenseId();
      $uploadTreeId = $itemId;
      $isRemoved = $clearingEvent->isRemoved();
      $type = ClearingEventTypes::USER;
      $reportInfo = $clearingEvent->getReportinfo();
      $comment = $clearingEvent->getComment();
      $acknowledgement = $clearingEvent->getAcknowledgement();
      $jobId = $this->jobId;
      $clearingEventIdsToCopy[] = $this->clearingDao->insertClearingEvent(
        $uploadTreeId, $userId, $groupId, $licenseId, $isRemoved,
        $type, $reportInfo, $comment, $acknowledgement, $jobId);
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

  /**
   * @brief Map clearing decisions by clearing id
   *
   * Creates array with clearing ids as key and ClearingDecision object as
   * value
   * @param ClearingDecision $clearingDecisions  Array of clearing decisions
   * @return ClearingDecision[]
   */
  public function mapByClearingId($clearingDecisions)
  {
    $mapped = array();

    foreach ($clearingDecisions as $clearingDecision) {
      $mapped[$clearingDecision->getClearingId()] = $clearingDecision;
    }

    return $mapped;
  }
}
