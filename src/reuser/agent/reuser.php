<?php
/*
 Copyright (C) 2014, Siemens AG
 Author: Daniele Fognini, Andreas Würl

 This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */


use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Application\UserInfo;
use Fossology\Lib\BusinessRules\AgentLicenseEventProcessor;
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

include_once(__DIR__ . "/version.php");

class ReuserAgent extends Agent
{

  /** @var int */
  private $conflictStrategyId;

  /** @var UploadDao */
  private $uploadDao;

  /** @var ClearingEventProcessor */
  private $clearingEventProcessor;

  /** @var ClearingDecisionProcessor */
  private $clearingDecisionProcessor;

  /** @var AgentLicenseEventProcessor */
  private $agentLicenseEventProcessor;

  /** @var ClearingDao */
  private $clearingDao;

  /** @var UserInfo */
  private $userInfo;

  /** @var DecisionTypes */
  private $decisionTypes;

  function __construct()
  {
    parent::__construct(REUSER_AGENT_NAME, AGENT_VERSION, AGENT_REV);

    $args = getopt("k:", array(""));
    $this->conflictStrategyId = array_key_exists('k', $args) ? $args['k'] : NULL;

    $this->uploadDao = $this->container->get('dao.upload');
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->decisionTypes = $this->container->get('decision.types');
    $this->clearingEventProcessor = $this->container->get('businessrules.clearing_event_processor');
    $this->clearingDecisionProcessor = $this->container->get('businessrules.clearing_decision_processor');
    $this->agentLicenseEventProcessor = $this->container->get('businessrules.agent_license_event_processor');
    $this->userInfo = new UserInfo();
  }


  function processUploadId($uploadId)
  {
    $reusedUploadId = $this->uploadDao->getReusedUpload($uploadId);
    $itemTreeBoundsReused = $this->uploadDao->getParentItemBounds($reusedUploadId);
    $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBoundsReused);

    $clearingDecisionByFileId = array();
    foreach ($clearingDecisions as $clearingDecision)
    {
      $fileId = $clearingDecision->getPfileId();
      $clearingDecisionByFileId[$fileId] = $clearingDecision;
    }

    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $containedItems = $this->uploadDao->getContainedItems(
        $itemTreeBounds,
        "pfile_fk = ANY($1)",
        array('{' . implode(', ', array_keys($clearingDecisionByFileId)) . '}')
    );

    foreach ($containedItems as $item)
    {
      $row = array('item' => $item);

      /** @var ClearingDecision $clearingDecision */
      $clearingDecision = $clearingDecisionByFileId[$item->getFileId()];
      $desiredLicenses = $clearingDecision->getPositiveLicenses();
      $row['decision'] = $desiredLicenses;

      list($added, $removed) = $this->clearingDecisionProcessor->getCurrentClearings(
          $item->getItemTreeBounds(), $this->userInfo->getUserId());

      $actualLicenses = array_map(function (ClearingResult $result)
      {
        return $result->getLicenseRef();
      }, $added);

      $toAdd = array_diff($desiredLicenses, $actualLicenses);
      $toRemove = array_diff($actualLicenses, $desiredLicenses);

      foreach ($toAdd as $license)
      {
        $this->insertHistoricalClearingEvent($clearingDecision, $item, $license, false);
      }

      foreach ($toRemove as $license)
      {
        $this->insertHistoricalClearingEvent($clearingDecision, $item, $license, true);
      }
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
  function insertHistoricalClearingEvent(ClearingDecision $clearingDecision, Item $item, LicenseRef $license, $remove)
  {
    $this->clearingDao->insertHistoricalClearingEvent(
        $clearingDecision->getDateAdded()->sub(new DateInterval('PT1S')),
        $item->getId(),
        $this->userInfo->getUserId(),
        $license->getId(),
        ClearingEventTypes::USER,
        $remove,
        '',
        ''
    );
  }

}

$agent = new ReuserAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->bail(0);