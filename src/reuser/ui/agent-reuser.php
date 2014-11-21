<?php
/***********************************************************
 * Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
 * Copyright (C) 2014, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/
use Fossology\Lib\Application\UserInfo;
use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\Clearing\ClearingResult;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\ClearingDecisionBuilder;
use Fossology\Lib\Data\LicenseMatch;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\Item;
use Fossology\Lib\Plugin\DefaultPlugin;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \file agent-reuser.php
 * \brief run the reuser license agent
 */

include_once(__DIR__ . "/../agent/version.php");

class ReuserAgentPlugin extends DefaultPlugin
{
  public $AgentName;

  function __construct()
  {
    parent::__construct("agent_reuser", array(
        self::TITLE => _("Automatic Clearing Decision Reuser"),
        self::PERMISSION => self::PERM_WRITE
    ));
    $this->AgentName = REUSER_AGENT_NAME;
  }

  /**
   * \brief  Register additional menus.
   */
  function RegisterMenus()
  {
    return 0;
  }

  /**
   * \brief Check if the upload has already been successfully scanned.
   *
   * \param $upload_pk
   *
   * \returns:
   * - 0 = no
   * - 1 = yes, from latest agent version
   * - 2 = yes, from older agent version
   **/
  function AgentHasResults($upload_pk)
  {
    return 0; /* this agent can be re run multiple times */
  }

  /**
   * \brief Queue the reuser agent.
   *  Before queuing, check if agent needs to be queued.  It doesn't need to be queued if:
   *  - It is already queued
   *  - It has already been run by the latest agent version
   *
   * @param int $job_pk
   * @param int $upload_pk
   * @param &string $ErrorMsg - error message on failure
   * @param array $Dependencies - array of plugin names representing dependencies.
   *        This is for dependencies that this plugin cannot know about ahead of time.
   * @param int|null $conflictStrategyId
   * @returns
   * - jq_pk Successfully queued
   * -   0   Not queued, latest version of agent has previously run successfully
   * -  -1   Not queued, error, error string in $ErrorMsg
   **/
  function AgentAdd($job_pk, $upload_pk, &$ErrorMsg, $Dependencies)
  {
    $Dependencies[] = "agent_adj2nest";
    return CommonAgentAdd($this, $job_pk, $upload_pk, $ErrorMsg, $Dependencies, $upload_pk);
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $vars = array();

    /** @var UploadDao $uploadDao */
    $uploadDao = $this->getObject('dao.upload');
    $uploadId = intval($request->get('uploadId'));

    if ($uploadId == 0)
    {
      return Response::create("", Response::HTTP_INTERNAL_SERVER_ERROR);
    }
    $reusedUploadId = intval($request->get('reuseUploadId'));
    if ($reusedUploadId === 0)
    {
      $reusedUploadId = $uploadDao->getReusedUpload($uploadId);
    }

    $vars['title'] = "reuser agent: upload $uploadId reuses decisions of upload $reusedUploadId";

    $itemTreeBounds = $uploadDao->getParentItemBounds($reusedUploadId);

    $vars['reusedTree'] = $itemTreeBounds;

    /** @var ClearingDao $clearingDao */
    $clearingDao = $this->getObject('dao.clearing');

    $clearingDecisions = $clearingDao->getFileClearingsFolder($itemTreeBounds);
    /** @var ClearingDecisionFilter $clearingDecisionFilter */
    $clearingDecisionFilter = $this->getObject('businessrules.clearing_decision_filter');
    $clearingDecisions = $clearingDecisionFilter->filterCurrentReusableClearingDecisions($clearingDecisions);

    $clearingDecisionByFileId = array();
    $fileIdsWithClearingDecision = array();
    $lines = array();
    foreach ($clearingDecisions as $clearingDecision)
    {
      $lines [] = strval($clearingDecision);
      $fileId = $clearingDecision->getPfileId();
      $fileIdsWithClearingDecision[] = $fileId;
      $clearingDecisionByFileId[$fileId] = $clearingDecision;
    }

    $newItemTreeBounds = $uploadDao->getParentItemBounds($uploadId);

    $containedItems = $uploadDao->getContainedItems($newItemTreeBounds, "pfile_fk = ANY($1)", array('{' . implode(', ', $fileIdsWithClearingDecision) . '}'));

    $timestamp = new DateTime();
    $userInfo = new UserInfo();
    $rows = array();
    /** @var ClearingDecisionProcessor $clearingDecisionProcessor */
    $clearingDecisionProcessor = $this->getObject('businessrules.clearing_decision_processor');
    foreach ($containedItems as $item)
    {
      $row = array('item' => $item);

      /** @var ClearingDecision $clearingDecision */
      $clearingDecision = $clearingDecisionByFileId[$item->getFileId()];
      $desiredLicenses = $clearingDecision->getPositiveLicenses();
      $row['decision'] = $desiredLicenses;

      list($added, $removed) = $clearingDecisionProcessor->getCurrentClearings($item->getItemTreeBounds(), $userInfo->getUserId());

      $actualLicenses = array_map(function (ClearingResult $result)
      {
        return $result->getLicenseRef();
      }, $added);

      $row['current'] = $actualLicenses;

      $toAdd = array_diff($desiredLicenses, $actualLicenses);
      $row['add'] = $toAdd;
      $toRemove = array_diff($actualLicenses, $desiredLicenses);
      $row['remove'] = $toRemove;

      foreach ($toAdd as $license)
      {
        //$this->insertHistoricalClearingEvent($clearingDao, $clearingDecision, $item, $userInfo, $license, false);
      }

      foreach ($toRemove as $license)
      {
        //$this->insertHistoricalClearingEvent($clearingDao, $clearingDecision, $item, $userInfo, $license, true);
      }

      $rows[] = $row;
    }
    $vars['results'] = $rows;

    return $this->render('reuser.html.twig', $this->mergeWithDefault($vars));
  }

  /**
   * @param $clearingDao
   * @param $clearingDecision
   * @param $item
   * @param $userInfo
   * @param $license
   * @param $remove
   */
  protected function insertHistoricalClearingEvent(ClearingDao $clearingDao, ClearingDecision $clearingDecision, Item $item, UserInfo $userInfo, LicenseRef $license, $remove)
  {
    $clearingDao->insertHistoricalClearingEvent(
        $clearingDecision->getDateAdded()->sub(new DateInterval('PT1S')),
        $item->getId(),
        $userInfo->getUserId(),
        $license->getId(),
        ClearingEventTypes::USER,
        $remove,
        '',
        ''
    );
  }
}

register_plugin(new ReuserAgentPlugin());
