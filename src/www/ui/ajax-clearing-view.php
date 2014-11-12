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
use Fossology\Lib\Dao\AgentsDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\Clearing\ClearingResult;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Util\LicenseOverviewPrinter;
use Fossology\Lib\View\HighlightProcessor;
use Fossology\Lib\View\LicenseProcessor;
use Fossology\Lib\View\LicenseRenderer;
use Monolog\Logger;

define("TITLE_ajaxClearingView", _("Change concluded License "));

class AjaxClearingView extends FO_Plugin
{
  /** @var UploadDao */
  private $uploadDao;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var AgentsDao */
  private $agentsDao;
  /** @var LicenseProcessor */
  private $licenseProcessor;
  /** @var LicenseOverviewPrinter */
  private $licenseOverviewPrinter;
  /** @var Logger */
  private $logger;
  /** @var HighlightDao */
  private $highlightDao;
  /** @var HighlightProcessor */
  private $highlightProcessor;
  /** @var ClearingDecisionProcessor */
  private $clearingDecisionEventProcessor;

  /** @var LicenseRenderer */

  function __construct()
  {
    $this->Name = "conclude-license";
    $this->Title = TITLE_ajaxClearingView;
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
    $this->agentsDao = $container->get('dao.agents');
    $this->licenseProcessor = $container->get('view.license_processor');
    $this->logger = $container->get("logger");

    $this->highlightDao = $container->get("dao.highlight");
    $this->highlightProcessor = $container->get("view.highlight_processor");
    $this->licenseRenderer = $container->get("view.license_renderer");

    $this->licenseOverviewPrinter = $container->get('utils.license_overview_printer');

    $this->clearingDecisionEventProcessor = $container->get('businessrules.clearing_decision_processor');
  }

  /**
   * @param $licenseShortName
   * @return string
   */
  protected function getLicenseFullTextLink($licenseShortName)
  {
    $uri = Traceback_uri() . '?mod=popup-license&lic=' . $licenseShortName;
    $licenseShortNameWithLink = "<a title=\"License Reference\" href=\"javascript:;\" onclick=\"javascript:window.open('$uri','License Text','width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes');\">$licenseShortName</a>";
    return $licenseShortNameWithLink;
  }

  /**
   * @param $orderAscending
   * @param $userId
   * @param $uploadId
   * @param $uploadTreeId
   * @internal param $itemTreeBounds
   * @return string
   */
  protected function doLicenses($orderAscending, $userId, $uploadId, $uploadTreeId)
  {
    $itemTreeBounds = $this->uploadDao->getItemTreeBoundsFromUploadId($uploadTreeId, $uploadId);

    $licenseRefs = $this->licenseDao->getLicenseRefs($_GET['sSearch'], $orderAscending);
    list($licenseDecisions, $removed) = $this->clearingDecisionEventProcessor->getCurrentClearings($itemTreeBounds, $userId);

    $licenses = array();
    foreach ($licenseRefs as $licenseRef)
    {
      $licenseShortName = $licenseRef->getShortName();

      if (array_key_exists($licenseShortName, $licenseDecisions))
        continue;

      $shortNameWithFullTextLink = $this->getLicenseFullTextLink($licenseShortName);
      $licenseId = $licenseRef->getId();
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
   * @param $userId
   * @param $uploadId
   * @param $uploadTreeId
   * @internal param $itemTreeBounds
   * @return string
   */
  protected function doClearings($orderAscending, $userId, $uploadId, $uploadTreeId)
  {
    $itemTreeBounds = $this->uploadDao->getItemTreeBoundsFromUploadId($uploadTreeId, $uploadId);
    $aaData = $this->getCurrentSelectedLicensesTableData($itemTreeBounds, $userId, $orderAscending);

    return json_encode(
        array(
            'sEcho' => intval($_GET['sEcho']),
            'aaData' => $aaData,
            'iTotalRecords' => count($aaData),
            'iTotalDisplayRecords' => count($aaData)));
  }

  function OutputOpen()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return null;
    }
    $uploadId = GetParm("upload", PARM_INTEGER);
    if (empty($uploadId))
    {
      return null;
    }

  }


  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return 0;
    }
    $output = $this->jsonContent();
    if (!$this->OutputToStdout)
    {
      $this->vars['content'] = $output;
      return;
    }
    if ($output === "success")
    {
      header('Content-type: text/plain');
      return $output;
    }
    header('Content-type: text/json');
    return $output;
  }


  protected function jsonContent()
  {
    global $SysConf;
    $userId = $SysConf['auth']['UserId'];
    $action = GetParm("do", PARM_STRING);
    if ($action)
    {
      switch ($action)
      {
        case "licenses":
        case "licenseDecisions":
        case "addLicense":
        case "removeLicense":
        case "setNextPrev":
        case "setNextPrevCopyRight":
        case "setNextPrevIp":
        case "setNextPrevEcc":
          $uploadId = GetParm("upload", PARM_INTEGER);
          if (empty($uploadId))
          {
            return;
          }
          $uploadTreeId = GetParm("item", PARM_INTEGER);
          if (empty($uploadTreeId))
          {
            return;
          }

          $licenseId = GetParm("licenseId", PARM_INTEGER);

          $sort0 = GetParm("sSortDir_0", PARM_STRING);
          if (isset($sort0))
          {
            $orderAscending = $sort0 === "asc";
          }
      }
      switch ($action)
      {
        case "licenses":
          return $this->doLicenses($orderAscending, $userId, $uploadId, $uploadTreeId);

        case "licenseDecisions":
          return $this->doClearings($orderAscending, $userId, $uploadId, $uploadTreeId);

        case "addLicense":
          $this->clearingDao->addClearing($uploadTreeId, $userId, $licenseId, ClearingEventTypes::USER);
          return json_encode(array());

        case "removeLicense":
          $this->clearingDao->removeClearing($uploadTreeId, $userId, $licenseId, ClearingEventTypes::USER);
          return json_encode(array());

        case "setNextPrev":
        case "setNextPrevCopyRight":
        case "setNextPrevIp":
        case "setNextPrevEcc":
          return $this->doNextPrev($action, $uploadId, $uploadTreeId);

        case "updateClearings":
          $id = GetParm("id", PARM_STRING);
          if (isset($id))
          {
            list ($uploadTreeId, $licenseId) = explode(',', $id);
            $what = GetParm("columnName", PARM_STRING);
            $changeTo = GetParm("value", PARM_STRING);
            $this->clearingDao->updateClearing($uploadTreeId, $userId, $licenseId, $what, $changeTo);
          }
          return "success";
      }
    }
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param $userId
   * @return array
   */
  protected function getCurrentSelectedLicensesTableData(ItemTreeBounds $itemTreeBounds, $userId, $orderAscending)
  {
    $uploadTreeId = $itemTreeBounds->getItemId();
    $uploadId = $itemTreeBounds->getUploadId();
    $uberUri = Traceback_uri() . "?mod=view-license" . Traceback_parm_keep(array('upload', 'folder'));

    list($addedClearingResults, $removedLicenses) = $this->clearingDecisionEventProcessor->getCurrentClearings($itemTreeBounds, $userId);
    $licenseEventTypes = new ClearingEventTypes();
    $licenseEventTypeMap = $licenseEventTypes->getMap();
    
    $table = array();
    foreach ($addedClearingResults as $licenseShortName => $clearingResult)
    {
      /** @var ClearingResult $clearingResult */
      $licenseId = $clearingResult->getLicenseId();

      $types = array();
      $reportInfo = "";
      $comment = "";

      if ($clearingResult->hasClearingEvent())
      {
        $licenseDecisionEvent = $clearingResult->getClearingEvent();
        $types[] = $licenseEventTypeMap[$licenseDecisionEvent->getEventType()];
        $reportInfo = $licenseDecisionEvent->getReportinfo();
        $comment = $licenseDecisionEvent->getComment();
      }

      $types = array_merge($types, $this->getAgentInfo($clearingResult, $uberUri, $uploadTreeId));

      $licenseShortNameWithLink = $this->getLicenseFullTextLink($licenseShortName);
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
        $licenseShortNameWithLink = $this->getLicenseFullTextLink($licenseShortName);
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

    $table = array_values($this->sortByKeys($table, $orderAscending));

    return $table;
  }

  /**
   * @param ClearingResult $licenseDecisionResult
   * @param $uberUri
   * @param $uploadTreeId
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
   * @param $orderAscending
   * @param $arrayToBeSortedByKeys
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
   * @param $action
   * @param $uploadId
   * @param $uploadTreeId
   * @return string
   */
  protected function doNextPrev($action, $uploadId, $uploadTreeId)
  {
    switch($action)
    {
      case "setNextPrev":
        $modName = "view-license";
        $opt = "option_skipFile";
        break;

      case "setNextPrevCopyRight":
        $modName = "copyright-view";
        $opt = "option_skipFileCopyRight";
        break;

      case "setNextPrevIp":
        $modName = "ip-view";
        $opt = "option_skipFileIp";
        break;

      case "setNextPrevEcc":
        $modName = "ecc-view";
        $opt = "option_skipFileEcc";
        break;
    }

    $options = array('skipThese' => GetParm($opt, PARM_STRING));
    $prevItem = $this->uploadDao->getPreviousItem($uploadId, $uploadTreeId, $options);
    $nextItem = $this->uploadDao->getNextItem($uploadId, $uploadTreeId, $options);

    if ($prevItem === null)
    {
      $prev = null;
    } else
    {
      $prev = $prevItem->getId();
    }

    if ($nextItem === null)
    {
      $next = null;
    } else
    {
      $next = $nextItem->getId();
    }


    return json_encode(array('prev' => $prev, 'next' => $next, 'uri' => Traceback_uri() . "?mod=" . $modName . Traceback_parm_keep(array('upload', 'folder'))));
  }
}

$NewPlugin = new AjaxClearingView();
