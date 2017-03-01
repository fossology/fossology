<?php
/*
 Copyright (C) 2014-2015, Siemens AG
 Author: Daniele Fognini, Andreas Würl

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

namespace Fossology\Reuser\Agent;

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
use Fossology\Reuser\Ui\ReuserAgentPlugin;

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
    foreach($this->uploadDao->getReusedUpload($uploadId, $this->groupId) as $reuseTriple)
    {
      $reusedUploadId = $reuseTriple['reused_upload_fk'];
      $reusedGroupId = $reuseTriple['reused_group_fk'];
      $reuseMode = $reuseTriple['reuse_mode'];
      $itemTreeBoundsReused = $this->uploadDao->getParentItemBounds($reusedUploadId);
      if (false === $itemTreeBoundsReused)
      {
        continue;
      }
      if($reuseMode & ReuserAgentPlugin::REUSE_ENHANCED){
        $this->processEnhancedUploadReuse($itemTreeBounds, $itemTreeBoundsReused, $reusedGroupId);
      }
      elseif($reuseMode & ReuserAgentPlugin::REUSE_MAIN){
        $this->reuseMainLicense($uploadId, $this->groupId, $reusedUploadId, $reusedGroupId);
        $this->processUploadReuse($itemTreeBounds, $itemTreeBoundsReused, $reusedGroupId);
      }
      elseif($reuseMode & ReuserAgentPlugin::REUSE_ENH_MAIN){
        $this->reuseMainLicense($uploadId, $this->groupId, $reusedUploadId, $reusedGroupId);
        $this->processEnhancedUploadReuse($itemTreeBounds, $itemTreeBoundsReused, $reusedGroupId);
      }
      else{
        $this->processUploadReuse($itemTreeBounds, $itemTreeBoundsReused, $reusedGroupId);
      }
    }
    return true;
  }  

  protected function reuseMainLicense($uploadId, $groupId, $reusedUploadId, $reusedGroupId)
  {
    $mainLicenseIds = $this->clearingDao->getMainLicenseIds($reusedUploadId, $reusedGroupId);
    if(!empty($mainLicenseIds))
    {
      foreach($mainLicenseIds as $mainLicenseId)
      {
        if(in_array($mainLicenseId, $this->clearingDao->getMainLicenseIds($uploadId, $groupId))){
          continue;
        }
        else{
          $this->clearingDao->makeMainLicense($uploadId, $groupId, $mainLicenseId);
        }
      }
    }
    return true;
  }

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

  protected function processEnhancedUploadReuse($itemTreeBounds, $itemTreeBoundsReused, $reusedGroupId)
  {
    $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBoundsReused, $reusedGroupId);
    $currenlyVisibleClearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $this->groupId);

    $currenlyVisibleClearingDecisionsById = $this->mapByClearingId($currenlyVisibleClearingDecisions);
    $clearingDecisionsById = $this->mapByClearingId($clearingDecisions);

    $clearingDecisionsToImport = array_diff_key($clearingDecisionsById,$currenlyVisibleClearingDecisionsById);

    $sql = "SELECT ut.* FROM uploadtree ur, uploadtree ut WHERE ur.upload_fk=$2 AND ur.pfile_fk=$3 AND ut.upload_fk=$1 AND ut.ufile_name=ur.ufile_name";
    $stmt = __METHOD__.'.reuseByName';
    $this->dbManager->prepare($stmt, $sql);
    $treeDao = $this->container->get('dao.tree');

    foreach($clearingDecisionsToImport as $clearingDecision)
    {
      $reusedPath = $treeDao->getRepoPathOfPfile($clearingDecision->getPfileId());
    
      $res = $this->dbManager->execute($stmt,array($itemTreeBounds->getUploadId(),$itemTreeBoundsReused->getUploadId(),$clearingDecision->getPfileId()));
      while($row = $this->dbManager->fetchArray($res))
      {
        $newPath = $treeDao->getRepoPathOfPfile($row['pfile_fk']);
        $this->copyClearingDecisionIfDifferenceIsSmall($reusedPath, $newPath, $clearingDecision, $row['uploadtree_pk']);
      }
      $this->dbManager->freeResult($res);
    }
  }
  
  protected function copyClearingDecisionIfDifferenceIsSmall($reusedPath,$newPath,$clearingDecision,$itemId)
  {
    $diffLevel = system("diff $reusedPath $newPath | wc -l");
    if($diffLevel===false)
    {
      throw new \Exception('cannot use diff tool');
    }
    if($diffLevel<5)
    {
      $this->createCopyOfClearingDecision($itemId, $this->userId, $this->groupId, $clearingDecision);
      $this->heartbeat(1);
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
