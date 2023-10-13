<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG
 Author: Daniele Fognini, Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\Clearing\ClearingResult;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\View\HighlightProcessor;
use Fossology\Lib\View\UrlBuilder;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AjaxClearingView extends FO_Plugin
{
  const OPTION_SKIP_FILE = "option_skipFile";
  const OPTION_SKIP_FILE_COPYRIGHT = "option_skipFileCopyRight";
  const OPTION_SKIP_FILE_IPRA = "option_skipFileIpra";
  const OPTION_SKIP_FILE_ECC = "option_skipFileEcc";
  const OPTION_SKIP_FILE_KEYWORD = "option_skipFileKeyword";

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
  /** @var DecisionTypes */
  private $decisionTypes;

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
    $this->decisionTypes = $container->get('decision.types');
    $this->clearingDecisionEventProcessor = $container->get('businessrules.clearing_decision_processor');
  }

  /**
   * @param int $groupId
   * @param int $uploadId
   * @param int $uploadTreeId
   * @return string
   */
  protected function doClearingHistory($groupId, $uploadId, $uploadTreeId)
  {
    $itemTreeBounds = $this->uploadDao->getItemTreeBoundsFromUploadId($uploadTreeId, $uploadId);

    $clearingDecWithLicenses = $this->clearingDao->getFileClearings($itemTreeBounds, $groupId, false, true);

    $table = array();
    $scope = new DecisionScopes();
    foreach ($clearingDecWithLicenses as $clearingDecision) {
      $licenseOutputs = array();
      foreach ($clearingDecision->getClearingLicenses() as $lic) {
        $shortName = $lic->getShortName();
        $licenseOutputs[$shortName] = $lic->isRemoved() ? "<span style=\"color:red\">$shortName</span>" : $shortName;
      }
      ksort($licenseOutputs, SORT_STRING);
      $row = array(
          '0' => date('Y-m-d', $clearingDecision->getTimeStamp()),
          '1' => $clearingDecision->getUserName(),
          '2' => $scope->getTypeName($clearingDecision->getScope()),
          '3' => $this->decisionTypes->getTypeName($clearingDecision->getType()),
          '4' => implode(", ", $licenseOutputs)
      );
      $table[] = $row;
    }
    return array(
      'sEcho' => intval($_GET['sEcho']),
      'aaData' => $table,
      'iTotalRecords' => count($table),
      'iTotalDisplayRecords' => count($table)
    );
  }

  /**
   * @param boolean $orderAscending
   * @param int $groupId
   * @param int $uploadId
   * @param int $uploadTreeId
   * @return string
   */
  protected function doLicenses($orderAscending, $groupId, $uploadId,
    $uploadTreeId)
  {
    $itemTreeBounds = $this->uploadDao->getItemTreeBoundsFromUploadId(
      $uploadTreeId, $uploadId);

    list ($licenseDecisions, $removed) = $this->clearingDecisionEventProcessor->getCurrentClearings($itemTreeBounds, $groupId);

    $licenseRefs = $this->licenseDao->getConclusionLicenseRefs(Auth::getGroupId(), $_GET['sSearch'], $orderAscending, array_keys($licenseDecisions));
    $licenses = array();
    foreach ($licenseRefs as $licenseRef) {
      $licenseId = $licenseRef->getId();
      $shortNameWithFullTextLink = $this->urlBuilder->getLicenseTextUrl($licenseRef);
      $actionLink = "<a href=\"javascript:;\" onClick=\"addLicense($uploadId, $uploadTreeId, $licenseId);\">"
                  . "<img src=\"images/space_16.png\" class=\"add\"/></a>";

      $licenses[] = array($shortNameWithFullTextLink, $actionLink);
    }
    return array(
            'sEcho' => intval($_GET['sEcho']),
            'aaData' => $licenses,
            'iTotalRecords' => count($licenses),
            'iTotalDisplayRecords' => count($licenses));
  }

  /**
   * @param $orderAscending
   * @param $groupId
   * @param $uploadId
   * @param $uploadTreeId
   * @return string
   *@internal param $itemTreeBounds
   */
  public function doClearings($orderAscending, $groupId, $uploadId, $uploadTreeId)
  {
    $itemTreeBounds = $this->uploadDao->getItemTreeBoundsFromUploadId($uploadTreeId, $uploadId);
    $aaData = $this->getCurrentSelectedLicensesTableData($itemTreeBounds,
      $groupId, $orderAscending);

    return array(
      'sEcho' => intval($_GET['sEcho']),
      'aaData' => $aaData,
      'iTotalRecords' => count($aaData),
      'iTotalDisplayRecords' => count($aaData)
    );
  }

  /**
   * @return Response
   */
  function Output()
  {
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();
    $action = GetParm("do", PARM_STRING);
    $uploadId = GetParm("upload", PARM_INTEGER);
    $uploadTreeId = GetParm("item", PARM_INTEGER);
    $licenseId = GetParm("licenseId", PARM_INTEGER);
    $sort0 = GetParm("sSortDir_0", PARM_STRING);

    $orderAscending = isset($sort0) ? $sort0 === "asc" : true;

    switch ($action) {
      case "licenses":
        return new JsonResponse(
          $this->doLicenses($orderAscending, $groupId, $uploadId, $uploadTreeId));

      case "licenseDecisions":
        return new JsonResponse(
          $this->doClearings($orderAscending, $groupId, $uploadId, $uploadTreeId));

      case "addLicense":
        $this->clearingDao->insertClearingEvent($uploadTreeId, $userId, $groupId,
          $licenseId, false, ClearingEventTypes::USER);
        return new JsonResponse();

      case "removeLicense":
        $this->clearingDao->insertClearingEvent($uploadTreeId, $userId, $groupId,
          $licenseId, true, ClearingEventTypes::USER);
        return new JsonResponse();

      case "makeMainLicense":
        $this->clearingDao->makeMainLicense($uploadId, $groupId, $licenseId);
        return new JsonResponse();

      case "removeMainLicense":
        $this->clearingDao->removeMainLicense($uploadId, $groupId, $licenseId);
        return new JsonResponse();

      case "setNextPrev":
      case "setNextPrevCopyRight":
      case "setNextPrevIpra":
      case "setNextPrevEcc":
      case "setNextPrevKeyword":
        return new JsonResponse(
          $this->doNextPrev($action, $uploadId, $uploadTreeId, $groupId));

      case "updateClearings":
        $id = GetParm("id", PARM_STRING);
        if (isset($id)) {
          list ($uploadTreeId, $licenseId) = explode(',', $id);
          $what = GetParm("columnId", PARM_INTEGER);
          if ($what==2) {
            $what = 'reportinfo';
          } elseif ($what==3) {
            $what = 'acknowledgement';
          } else {
            $what = 'comment';
          }
          $changeTo = GetParm("value", PARM_RAW);
          $this->clearingDao->updateClearingEvent($uploadTreeId, $userId, $groupId, $licenseId, $what, $changeTo);
        }
        return $this->createPlainResponse("success");

      case "showClearingHistory":
        return new JsonResponse($this->doClearingHistory($groupId, $uploadId, $uploadTreeId));

      case "checkCandidateLicense":
        if (!empty($this->clearingDao->getCandidateLicenseCountForCurrentDecisions($uploadTreeId))) {
          return $this->createPlainResponse("Cannot add candidate license as global decision");
        }
        return $this->createPlainResponse("success");

      default:
        return $this->createPlainResponse("fail");
    }
  }

  /**
   * @param id $uploadtreeid,$licenseid
   * @return string with attr
   */
  protected function getBuildClearingsForSingleFile($uploadTreeId, $licenseId, $forValue, $what, $detectorType=0)
  {
    $classAttr = "color:#000000;";
    $value = "Click to add";
    if (empty($forValue) && $detectorType == 2 && $what == 2) {
      $classAttr = "color:red;font-weight:bold;";
    }

    if (!empty($forValue)) {
      $value = convertToUTF8(substr(ltrim($forValue, " \t\n"), 0, 15)."...");
    }
    return "<a href=\"javascript:;\" style='$classAttr' id='clearingsForSingleFile$licenseId$what' onclick=\"openTextModel($uploadTreeId, $licenseId, $what);\" title='".convertToUTF8(htmlspecialchars($forValue, ENT_QUOTES), true)."'>$value</a>";
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @param boolean $orderAscending
   * @return array
   */
  public function getCurrentSelectedLicensesTableData(ItemTreeBounds $itemTreeBounds, $groupId, $orderAscending)
  {
    $uploadTreeId = $itemTreeBounds->getItemId();
    $uploadId = $itemTreeBounds->getUploadId();
    $uberUri = Traceback_uri() . "?mod=view-license" . Traceback_parm_keep(array(
        'upload',
        'folder'
      ));

    list ($addedClearingResults, $removedLicenses) = $this->clearingDecisionEventProcessor->getCurrentClearings(
      $itemTreeBounds, $groupId, LicenseMap::CONCLUSION);
    $licenseEventTypes = new ClearingEventTypes();

    $mainLicIds = $this->clearingDao->getMainLicenseIds($uploadId, $groupId);

    $table = array();
    /* @var $clearingResult ClearingResult */
    foreach ($addedClearingResults as $licenseShortName => $clearingResult) {
      $licenseId = $clearingResult->getLicenseId();

      $types = $this->getAgentInfo($clearingResult, $uberUri, $uploadTreeId);
      $reportInfo = "";
      $comment = "";
      $acknowledgement = "";

      if ($clearingResult->hasClearingEvent()) {
        $licenseDecisionEvent = $clearingResult->getClearingEvent();
        $types[] = $this->getEventInfo($licenseDecisionEvent, $uberUri,
          $uploadTreeId, $licenseEventTypes);
        $reportInfo = $licenseDecisionEvent->getReportinfo();
        $comment = $licenseDecisionEvent->getComment();
        $acknowledgement = $licenseDecisionEvent->getAcknowledgement();
      }

      $licenseShortNameWithLink = $this->urlBuilder->getLicenseTextUrl(
        $clearingResult->getLicenseRef());
      $actionLink = "<a href=\"javascript:;\" onclick=\"removeLicense($uploadId, $uploadTreeId, $licenseId);\"><img class=\"delete\" src=\"images/space_16.png\" alt=\"\"/></a>";
      if (in_array($clearingResult->getLicenseId(), $mainLicIds)) {
        $tooltip = _('This is a main license for the upload. Click to discard selection.');
        $actionLink .= " <a href=\"javascript:;\" onclick=\"removeMainLicense($uploadId, $licenseId);\"><img src=\"images/icons/star_filled_16.png\" alt=\"mainLicense\" title=\"$tooltip\" border=\"0\"/></a>";
      } else {
        $tooltip = _('Click to select this as a main license for the upload.');
        $actionLink .= " <a href=\"javascript:;\" onclick=\"makeMainLicense($uploadId, $licenseId);\"><img src=\"images/icons/star_16.png\" alt=\"noMainLicense\" title=\"$tooltip\" border=\"0\"/></a>";
      }
      $detectorType = $this->licenseDao->getLicenseById($clearingResult->getLicenseId(), $groupId)->getDetectorType();
      $id = "$uploadTreeId,$licenseId";
      $reportInfoField = $this->getBuildClearingsForSingleFile($uploadTreeId, $licenseId, $reportInfo, 2, $detectorType);
      $acknowledgementField = $this->getBuildClearingsForSingleFile($uploadTreeId, $licenseId, $acknowledgement, 3);
      $commentField = $this->getBuildClearingsForSingleFile($uploadTreeId, $licenseId, $comment, 4);

      $table[$licenseShortName] = array('DT_RowId' => $id,
          '0' => $actionLink,
          '1' => $licenseShortNameWithLink,
          '2' => implode("<br/>", $types),
          '3' => $reportInfoField,
          '4' => $acknowledgementField,
          '5' => $commentField);
    }

    foreach ($removedLicenses as $licenseShortName => $clearingResult) {
      if ($clearingResult->getAgentDecisionEvents()) {
        $agents = $this->getAgentInfo($clearingResult, $uberUri, $uploadTreeId);
        $licenseShortNameWithLink = $this->urlBuilder->getLicenseTextUrl($clearingResult->getLicenseRef());
        $licenseId = $clearingResult->getLicenseId();
        $actionLink = "<a href=\"javascript:;\" onclick=\"addLicense($uploadId, $uploadTreeId, $licenseId);\"><img class=\"add\" src=\"images/space_16.png\" alt=\"\"/></a>";
        $filled = in_array($clearingResult->getLicenseId(), $mainLicIds) ? 'filled_' : '';
        $actionLink .= ' <img src="images/icons/star_'.$filled.'16.png" alt="mainLicense"/>';

        $idArray = array($uploadTreeId, $licenseId);
        $id = implode(',', $idArray);
        $table[$licenseShortName] = array('DT_RowId' => $id,
            'DT_RowClass' => 'removed',
            '0' => $actionLink,
            '1' => $licenseShortNameWithLink,
            '2' => implode("<br/>", $agents),
            '3' => "-",
            '4' => "-",
            '5' => "-");
      }
    }

    return array_values($this->sortByKeys($table, $orderAscending));
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
    foreach ($licenseDecisionResult->getAgentDecisionEvents() as $agentDecisionEvent) {
      $agentId = $agentDecisionEvent->getAgentId();
      $matchId = $agentDecisionEvent->getMatchId();
      $highlightRegion = $this->highlightDao->getHighlightRegion($matchId);
      $uri = null;
      $percentage = false;
      if ($highlightRegion[0] != "" && $highlightRegion[1] != "") {
        $percentage = $agentDecisionEvent->getPercentage();
        $page = $this->highlightDao->getPageNumberOfHighlightEntry($matchId);
        $uri = $uberUri . "&item=$uploadTreeId&agentId=$agentId&highlightId=$matchId&page=$page#highlight";
      }
      $agentResults[$agentDecisionEvent->getAgentName()][] = array(
        "uri" => $uri,
        "text" => $percentage ? " (" . $percentage . " %)" : ""
      );
    }

    $results = array();
    foreach ($agentResults as $agentName => $agentResult) {
      $matchTexts = array();

      foreach ($agentResult as $index => $agentData) {
        $uri = $agentData['uri'];
        if (! empty($uri)) {
          $matchTexts[] = "<a href=\"$uri\">#" . ($index + 1) . "</a>" . $agentData['text'];
        } else {
          $matchTexts[] = $agentData['text'];
        }
      }
      $matchTexts = implode(', ', $matchTexts);
      $results[] = $agentName . (empty($matchTexts) ? "" : ": $matchTexts");
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

    if ($orderAscending) {
      return array_reverse($arrayToBeSortedByKeys);
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
    switch ($action) {
      case "setNextPrev":
        $modName = "view-license";
        $opt = self::OPTION_SKIP_FILE;
        break;

      case "setNextPrevCopyRight":
        $modName = "copyright-view";
        $opt = self::OPTION_SKIP_FILE_COPYRIGHT;
        break;

      case "setNextPrevIpra":
        $modName = "ipra-view";
        $opt = self::OPTION_SKIP_FILE_IPRA;
        break;

      case "setNextPrevEcc":
        $modName = "ecc-view";
        $opt = self::OPTION_SKIP_FILE_ECC;
        break;
      case "setNextPrevKeyword":
        $modName = "keyword-view";
        $opt = self::OPTION_SKIP_FILE_KEYWORD;
        break;
    }

    $options = array('skipThese' => GetParm($opt, PARM_STRING), 'groupId' => $groupId);

    $prevItem = $this->uploadDao->getPreviousItem($uploadId, $uploadTreeId, $options);
    $prevItemId = $prevItem ? $prevItem->getId() : null;

    $nextItem = $this->uploadDao->getNextItem($uploadId, $uploadTreeId, $options);
    $nextItemId = $nextItem ? $nextItem->getId() : null;

    return array('prev' => $prevItemId, 'next' => $nextItemId,
      'uri' => Traceback_uri() . "?mod=" . $modName . Traceback_parm_keep(array('upload', 'folder')));
  }

  /**
   * @param $output
   * @return Response
   */
  private function createPlainResponse($output)
  {
    return new Response($output, Response::HTTP_OK, array('Content-type' => 'text/plain'));
  }

  private function getEventInfo($licenseDecisionEvent, $uberUri, $uploadTreeId, $licenseEventTypes)
  {
    $type = $licenseEventTypes->getTypeName($licenseDecisionEvent->getEventType());
    if ($licenseDecisionEvent->getEventType() == ClearingEventTypes::BULK) {
      $eventId = $licenseDecisionEvent->getEventId();
      $type .= ': <a href="'.$uberUri."&item=$uploadTreeId&clearingId=".$eventId.'#highlight">#'.$eventId.'</a>';
    }
    return $type;
  }
}

$NewPlugin = new AjaxClearingView();
