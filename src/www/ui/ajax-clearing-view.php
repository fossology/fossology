<?php
/*
 Copyright (C) 2014, Siemens AG
 Author: Daniele Fognini, Johannes Najjar

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

use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\Clearing\ClearingResult;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\View\HighlightProcessor;
use Fossology\Lib\View\UrlBuilder;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonRespose;

class AjaxClearingView extends FO_Plugin
{
  const OPTION_SKIP_FILE = "option_skipFile";
  const OPTION_SKIP_FILE_COPYRIGHT = "option_skipFileCopyRight";
  const OPTION_SKIP_FILE_IP = "option_skipFileIp";
  const OPTION_SKIP_FILE_ECC = "option_skipFileEcc";

  /** @var UploadDao */
  private $uploadDao;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var AgentDao */
  private $agentsDao;
  /** @var Logger */
  private $logger;
  /** @var HighlightDao */
  private $highlightDao;
  /** @var HighlightProcessor */
  private $highlightProcessor;
  /** @var ClearingDecisionProcessor */
  private $clearingDecisionEventProcessor;
  /** @var UrlBuilder */
  private $urlBuilder;

  function __construct()
  {
    $this->Name = "conclude-license";
    $this->Title = _("Change concluded License ");
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->Dependency = array("view");
    $this->LoginFlag = 0;
    $this->NoMenu = 0;
    $this->OutputType = 'JSON';
    $this->OutputToStdout = true;
    parent::__construct();

    global $container;
    $this->licenseDao = $container->get('dao.license');
    $this->uploadDao = $container->get('dao.upload');
    $this->clearingDao = $container->get('dao.clearing');
    $this->agentsDao = $container->get('dao.agent');
    $this->logger = $container->get("logger");

    $this->highlightDao = $container->get("dao.highlight");
    $this->highlightProcessor = $container->get("view.highlight_processor");
    $this->urlBuilder = $container->get('view.url_builder');

    $this->clearingDecisionEventProcessor = $container->get('businessrules.clearing_decision_processor');
  }


  /**
   * @param boolean $orderAscending
   * @param int $groupId
   * @param int $uploadId
   * @param int $uploadTreeId
   * @return string
   */
  protected function doLicenses($orderAscending, $groupId, $uploadId, $uploadTreeId)
  {
    $itemTreeBounds = $this->uploadDao->getItemTreeBoundsFromUploadId($uploadTreeId, $uploadId);

    list($licenseDecisions, $removed) = $this->clearingDecisionEventProcessor->getCurrentClearings($itemTreeBounds, $groupId);

    $licenseRefs = $this->licenseDao->getConclusionLicenseRefs($_SESSION['GroupId'], $_GET['sSearch'], $orderAscending, array_keys($licenseDecisions));
    $licenses = array();
    foreach ($licenseRefs as $licenseRef)
    {
      $licenseId = $licenseRef->getId();
      $shortNameWithFullTextLink = $this->urlBuilder->getLicenseTextUrl($licenseRef);
      $actionLink = "<a href=\"javascript:;\" onClick=\"addLicense($uploadId, $uploadTreeId, $licenseId);\"><div class='add'></div></a>";

      $licenses[] = array($shortNameWithFullTextLink, $actionLink);
    }
    return json_encode(
        array(
            'sEcho' => intval($_GET['sEcho']),
            'aaData' => $licenses,
            'iTotalRecords' => count($licenses),
            'iTotalDisplayRecords' => count($licenses)));
  }

  /**
   * @param $orderAscending
   * @param $groupId
   * @param $uploadId
   * @param $uploadTreeId
   * @internal param $itemTreeBounds
   * @return string
   */
  protected function doClearings($orderAscending, $groupId, $uploadId, $uploadTreeId)
  {
    $itemTreeBounds = $this->uploadDao->getItemTreeBoundsFromUploadId($uploadTreeId, $uploadId);
    $aaData = $this->getCurrentSelectedLicensesTableData($itemTreeBounds, $groupId, $orderAscending);

    return array(
        'sEcho' => intval($_GET['sEcho']),
        'aaData' => $aaData,
        'iTotalRecords' => count($aaData),
        'iTotalDisplayRecords' => count($aaData));
  }

  /**
   * @return Response
   */
  function Output()
  {
    global $SysConf;
    $userId = $SysConf['auth']['UserId'];
    $groupId = $SysConf['auth']['GroupId'];
    $action = GetParm("do", PARM_STRING);
    $uploadId = GetParm("upload", PARM_INTEGER);
    $uploadTreeId = GetParm("item", PARM_INTEGER);
    $licenseId = GetParm("licenseId", PARM_INTEGER);
    $sort0 = GetParm("sSortDir_0", PARM_STRING);

    $orderAscending = isset($sort0) ? $sort0 === "asc" : true;

    switch ($action)
    {
      case "licenses":
        return new JsonResponse($this->doLicenses($orderAscending, $groupId, $uploadId, $uploadTreeId));

      case "licenseDecisions":
        return new JsonResponse($this->doClearings($orderAscending, $groupId, $uploadId, $uploadTreeId));

      case "addLicense":
        $this->clearingDao->insertClearingEvent($uploadTreeId, $userId, $groupId, $licenseId, false, ClearingEventTypes::USER);
        return new JsonResponse();

      case "removeLicense":
        $this->clearingDao->insertClearingEvent($uploadTreeId, $userId, $groupId, $licenseId, true, ClearingEventTypes::USER);
        return new JsonResponse();

      case "setNextPrev":
      case "setNextPrevCopyRight":
      case "setNextPrevIp":
      case "setNextPrevEcc":
        return new JsonResponse($this->doNextPrev($action, $uploadId, $uploadTreeId, $groupId));

      case "updateClearings":
        $id = GetParm("id", PARM_STRING);
        if (isset($id))
        {
          list ($uploadTreeId, $licenseId) = explode(',', $id);
          $what = GetParm("columnName", PARM_STRING);
          $changeTo = GetParm("value", PARM_STRING);
          $this->clearingDao->updateClearingEvent($uploadTreeId, $userId, $groupId, $licenseId, $what, $changeTo);
        }
        return $this->createPlainResponse("success");

      default:
        return $this->createPlainResponse("fail");
    }
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @param boolean $orderAscending
   * @return array
   */
  protected function getCurrentSelectedLicensesTableData(ItemTreeBounds $itemTreeBounds, $groupId, $orderAscending)
  {
    $uploadTreeId = $itemTreeBounds->getItemId();
    $uploadId = $itemTreeBounds->getUploadId();
    $uberUri = Traceback_uri() . "?mod=view-license" . Traceback_parm_keep(array('upload', 'folder'));

    list($addedClearingResults, $removedLicenses) = $this->clearingDecisionEventProcessor->getCurrentClearings($itemTreeBounds, $groupId);
    $licenseEventTypes = new ClearingEventTypes();

    $table = array();
    /** @var ClearingResult $clearingResult */
    foreach ($addedClearingResults as $licenseShortName => $clearingResult)
    {
      $licenseId = $clearingResult->getLicenseId();

      $types = $this->getAgentInfo($clearingResult, $uberUri, $uploadTreeId);
      $reportInfo = "";
      $comment = "";

      if ($clearingResult->hasClearingEvent())
      {
        $licenseDecisionEvent = $clearingResult->getClearingEvent();
        $types[] = $licenseEventTypes->getTypeName($licenseDecisionEvent->getEventType());
        $reportInfo = $licenseDecisionEvent->getReportinfo();
        $comment = $licenseDecisionEvent->getComment();
      }

      $licenseShortNameWithLink = $this->urlBuilder->getLicenseTextUrl($clearingResult->getLicenseRef());
      $actionLink = "<a href=\"javascript:;\" onClick=\"removeLicense($uploadId, $uploadTreeId, $licenseId);\"><div class='delete'></div></a>";

      $reportInfoField = $reportInfo;
      $commentField = $comment;

      $id = "$uploadTreeId,$licenseId";
      $table[$licenseShortName] = array('DT_RowId' => $id,
          '0' => $licenseShortNameWithLink,
          '1' => implode("<br/>", $types),
          '2' => $reportInfoField,
          '3' => $commentField,
          '4' => $actionLink);
    }

    foreach ($removedLicenses as $licenseShortName => $clearingResult)
    {
      if ($clearingResult->getAgentDecisionEvents())
      {
        $agents = $this->getAgentInfo($clearingResult, $uberUri, $uploadTreeId);
        $licenseShortNameWithLink = $this->urlBuilder->getLicenseTextUrl($clearingResult->getLicenseRef());
        $licenseId = $clearingResult->getLicenseId();
        $actionLink = "<a href=\"javascript:;\" onClick=\"addLicense($uploadId, $uploadTreeId, $licenseId);\"><div class='add'></div></a>";

        $idArray = array($uploadTreeId, $licenseId);
        $id = implode(',', $idArray);
        $table[$licenseShortName] = array('DT_RowId' => $id,
            'DT_RowClass' => 'removed',
            '0' => $licenseShortNameWithLink,
            '1' => implode("<br/>", $agents),
            '2' => "-",
            '3' => "-",
            '4' => $actionLink);
      }
    }

    $valueTable = array_values($this->sortByKeys($table, $orderAscending));
    return $valueTable;
  }

  /**
   * @param ClearingResult $licenseDecisionResult
   * @param string $uberUri
   * @param int $uploadTreeId
   * @return array
   */
  protected function getAgentInfo($licenseDecisionResult, $uberUri, $uploadTreeId)
  {
    $agentResults = array();
    foreach ($licenseDecisionResult->getAgentDecisionEvents() as $agentDecisionEvent)
    {
      $licenseId = $agentDecisionEvent->getLicenseId();
      $agentId = $agentDecisionEvent->getAgentId();
      $matchId = $agentDecisionEvent->getMatchId();
      $percentage = $agentDecisionEvent->getPercentage();
      $agentResults[$agentDecisionEvent->getAgentName()][] = array(
          "uri" => $uberUri . "&item=$uploadTreeId&agentId=$agentId&licenseId=$licenseId&highlightId=$matchId#highlight",
          "text" => $percentage ? " (" . $percentage . " %)" : ""
      );
    }

    $results = array();
    foreach ($agentResults as $agentName => $agentResult)
    {
      $matchTexts = array();

      foreach ($agentResult as $index => $agentData)
      {
        $uri = $agentData['uri'];
        $matchTexts[] = "<a href=\"$uri\">#" . ($index + 1) . "</a>" . $agentData['text'];
      }
      $results[] = $agentName . ": " . implode(', ', $matchTexts);
    }
    return $results;
  }

  /**
   * @param $arrayToBeSortedByKeys
   * @param $orderAscending
   * @return array
   */
  protected function sortByKeys($arrayToBeSortedByKeys, $orderAscending)
  {
    ksort($arrayToBeSortedByKeys, SORT_STRING);

    if ($orderAscending)
    {
      $arrayToBeSortedByKeys = array_reverse($arrayToBeSortedByKeys);
      return $arrayToBeSortedByKeys;
    }
    return $arrayToBeSortedByKeys;
  }

  /**
   * @param string $action
   * @param int $uploadId
   * @param int $uploadTreeId
   * @param int $groupId
   * @return string
   */
  protected function doNextPrev($action, $uploadId, $uploadTreeId, $groupId)
  {
    switch ($action)
    {
      case "setNextPrev":
        $modName = "view-license";
        $opt = self::OPTION_SKIP_FILE;
        break;

      case "setNextPrevCopyRight":
        $modName = "copyright-view";
        $opt = self::OPTION_SKIP_FILE_COPYRIGHT;
        break;

      case "setNextPrevIp":
        $modName = "ip-view";
        $opt = self::OPTION_SKIP_FILE_IP;
        break;

      case "setNextPrevEcc":
        $modName = "ecc-view";
        $opt = self::OPTION_SKIP_FILE_ECC;
        break;
    }

    $options = array('skipThese' => GetParm($opt, PARM_STRING), 'groupId' => $groupId);

    $prevItem = $this->uploadDao->getPreviousItem($uploadId, $uploadTreeId, $options);
    $prevItemId = $prevItem ? $prevItem->getId() : null;

    $nextItem = $this->uploadDao->getNextItem($uploadId, $uploadTreeId, $options);
    $nextItemId = $nextItem ? $nextItem->getId() : null;

    return array('prev' => $prevItemId, 'next' => $nextItemId, 'uri' => Traceback_uri() . "?mod=" . $modName . Traceback_parm_keep(array('upload', 'folder')));
  }

  /**
   * @param $output
   * @return Response
   */
  private function createPlainResponse($output)
  {
    return new Response($output, Response::HTTP_OK, array('Content-type' => 'text/plain'));
  }
}

$NewPlugin = new AjaxClearingView();
