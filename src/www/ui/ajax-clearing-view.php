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
use Fossology\Lib\BusinessRules\ClearingDecisionEventProcessor;
use Fossology\Lib\Dao\AgentsDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\LicenseDecision\LicenseDecisionResult;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Util\ChangeLicenseUtility;
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
  /** @var ChangeLicenseUtility */
  private $changeLicenseUtility;
  /** @var LicenseOverviewPrinter */
  private $licenseOverviewPrinter;
  /** @var Logger */
  private $logger;
  /** @var HighlightDao */
  private $highlightDao;
  /** @var HighlightProcessor */
  private $highlightProcessor;
  /** @var ClearingDecisionEventProcessor */
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

    $this->changeLicenseUtility = $container->get('utils.change_license_utility');
    $this->licenseOverviewPrinter = $container->get('utils.license_overview_printer');

    $this->clearingDecisionEventProcessor = $container->get('businessrules.clearing_decision_event_processor');
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
   * @param $itemTreeBounds
   * @param $userId
   * @param $uploadId
   * @param $uploadTreeId
   * @return string
   */
  protected function doLicenses($orderAscending, $userId, $uploadId, $uploadTreeId)
  {
    $itemTreeBounds = $this->uploadDao->getFileTreeBoundsFromUploadId($uploadTreeId, $uploadId);

    $licenseRefs = $this->licenseDao->getLicenseRefs($_GET['sSearch'], $orderAscending);
    list($licenseDecisions, $removed) = $this->clearingDecisionEventProcessor->getCurrentLicenseDecisions($itemTreeBounds, $userId);
    $licenses = array();
    foreach ($licenseRefs as $licenseRef)
    {
      $currentShortName = $licenseRef->getShortName();
      if (array_key_exists($currentShortName, $licenseDecisions)) continue;
      $shortNameWithFullTextLink = $this->getLicenseFullTextLink($currentShortName);
      $theLicenseId = $licenseRef->getId();
      $actionLink = "<a href=\"javascript:;\" onClick=\"addLicense($uploadId, $uploadTreeId, $theLicenseId);\"><img src=\"images/icons/add_16.png\"></a>";

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
  protected function doLicenseDecisions($orderAscending, $userId, $uploadId, $uploadTreeId)
  {
    $itemTreeBounds = $this->uploadDao->getFileTreeBoundsFromUploadId($uploadTreeId, $uploadId);
    $aaData = $this->getCurrentLicenseDecisions($itemTreeBounds, $userId, $orderAscending);

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

          $global = GetParm("global", PARM_STRING) === "true";

      }
      switch ($action)
      {
        case "licenses":
          return $this->doLicenses($orderAscending, $userId, $uploadId, $uploadTreeId);

        case "licenseDecisions":
          return $this->doLicenseDecisions($orderAscending, $userId, $uploadId, $uploadTreeId);

        case "addLicense":
          $this->clearingDao->addLicenseDecision($uploadTreeId, $userId, $licenseId, 1, $global); //$global was always false, why?
          return json_encode(array());

        case "removeLicense":
          $this->clearingDao->removeLicenseDecision($uploadTreeId, $userId, $licenseId, 1, $global);
          return json_encode(array());

        case "setNextPrev":
        case "setNextPrevCopyRight":
          if ($action == "setNextPrevCopyRight")
          {
            $modName = "copyright-view";
            $opt = "option_skipFileCopyRight";
          } else
          {
            $modName = "view-license";
            $opt = "option_skipFile";
          }

          $options = array('skipThese' => GetParm($opt, PARM_STRING));
          $prev = $this->uploadDao->getPreviousItem($uploadId, $uploadTreeId, $options);
          $next = $this->uploadDao->getNextItem($uploadId, $uploadTreeId, $options);


          return json_encode(array('prev' => $prev, 'next' => $next, 'uri' => Traceback_uri() . "?mod=" . $modName . Traceback_parm_keep(array('upload', 'folder'))));

        case "updateLicenseDecisions":
          $id = GetParm("id", PARM_STRING);
          if (isset($id))
          {
            list ($uploadTreeId, $licenseId) = explode(',', $id);
            $what = GetParm("columnName", PARM_STRING);
            $changeTo = GetParm("value", PARM_STRING);
            $this->clearingDao->updateLicenseDecision($uploadTreeId, $userId, $licenseId, $what, $changeTo);
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
  protected function getCurrentLicenseDecisions(ItemTreeBounds $itemTreeBounds, $userId, $orderAscending)
  {
    $uploadTreeId = $itemTreeBounds->getUploadTreeId();
    $uploadId = $itemTreeBounds->getUploadId();


    $uberUri = Traceback_uri() . "?mod=view-license" . Traceback_parm_keep(array('upload', 'folder'));

    list($licenseDecisions, $removedLicenses) = $this->clearingDecisionEventProcessor->getCurrentLicenseDecisions($itemTreeBounds, $userId);


    $table = array();
    foreach ($licenseDecisions as $licenseShortName => $licenseDecisionResult)
    {
      /** @var LicenseDecisionResult $licenseDecisionResult */
      $licenseId = $licenseDecisionResult->getLicenseId();

      $types = array();
      $reportInfo = "";
      $comment = "";

      if ($licenseDecisionResult->hasLicenseDecisionEvent())
      {
        $licenseDecisionEvent = $licenseDecisionResult->getLicenseDecisionEvent();
        $types[] = $licenseDecisionEvent->getEventType();
        $reportInfo = $licenseDecisionEvent->getReportinfo();
        $comment = $licenseDecisionEvent->getComment();
      }

      $types = array_merge($types, $this->getAgentInfo($licenseDecisionResult, $uberUri, $uploadTreeId));

      $licenseShortNameWithLink = $this->getLicenseFullTextLink($licenseShortName);
      $actionLink = "<a href=\"javascript:;\" onClick=\"removeLicense($uploadId, $uploadTreeId, $licenseId);\"><img src=\"images/icons/close_16.png\"></a>";

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

    foreach ($removedLicenses as $licenseShortName => $licenseDecisionResult)
    {
      if ($licenseDecisionResult->getAgentDecisionEvents()) {
        $agents = $this->getAgentInfo($licenseDecisionResult, $uberUri, $uploadTreeId);
        $licenseShortNameWithLink = $this->getLicenseFullTextLink($licenseShortName);
        $licenseId = $licenseDecisionResult->getLicenseId();
        $actionLink = "<a href=\"javascript:;\" onClick=\"addLicense($uploadId, $uploadTreeId, $licenseId);\"><img src=\"images/icons/add_16.png\"></a>";

        $idArray = array($uploadTreeId, $licenseId);
        $id = implode(',', $idArray);
        $table[$licenseShortName] = array('DT_RowId' => $id,
            '0' => $licenseShortNameWithLink,
            '1' => implode("<br/>", $agents),
            '2' => "n/a",
            '3' => "n/a",
            '4' => $actionLink);
      }
    }

    $table = array_values($this->sortByKeys($table, $orderAscending));

    return $table;
  }

  /**
   * @param LicenseDecisionResult $licenseDecisionResult
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
}

$NewPlugin = new AjaxClearingView();