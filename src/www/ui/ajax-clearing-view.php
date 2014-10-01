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
use Fossology\Lib\Dao\Data\FileTreeBounds;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
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
   * @param $fileTreeBounds
   * @param $userId
   * @param $uploadId
   * @param $uploadTreeId
   * @return string
   */
  protected function doLicenses($orderAscending, $userId, $uploadId, $uploadTreeId)
  {
    $fileTreeBounds = $this->uploadDao->getFileTreeBoundsFromUploadId($uploadTreeId, $uploadId);

    $licenseRefs = $this->licenseDao->getLicenseRefs($_GET['sSearch'], $orderAscending);
    list($licenseDecisions, $removed) = $this->clearingDecisionEventProcessor->getCurrentLicenseDecisions($fileTreeBounds, $userId);
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
   * @internal param $fileTreeBounds
   * @return string
   */
  protected function doLicenseDecisions($orderAscending, $userId, $uploadId, $uploadTreeId)
  {
    $fileTreeBounds = $this->uploadDao->getFileTreeBoundsFromUploadId($uploadTreeId,$uploadId);
    $aaData = $this->getCurrentLicenseDecisions($fileTreeBounds, $userId, $orderAscending);

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

          // $orderAscending = $_GET['sSortDir_0'] === "asc";
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
          return $this->doLicenseDecisions($orderAscending, $userId, $uploadId, $uploadTreeId);

        case "addLicense":
          $this->clearingDao->addLicenseDecision($uploadTreeId, $userId, $licenseId, 1, false);
          return json_encode(array());

        case "removeLicense":
          $this->clearingDao->removeLicenseDecision($uploadTreeId, $userId, $licenseId, 1, false);
          return json_encode(array());

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
   * @param FileTreeBounds $fileTreeBounds
   * @param $userId
   * @return array
   */
  protected function getCurrentLicenseDecisions(FileTreeBounds $fileTreeBounds, $userId, $orderAscending)
  {
    $uploadTreeId = $fileTreeBounds->getUploadTreeId();
    $uploadId = $fileTreeBounds->getUploadId();


    $uberUri = Traceback_uri() . "?mod=view-license" . Traceback_parm_keep(array('upload', 'folder'));

    list($licenseDecisions, $removed) = $this->clearingDecisionEventProcessor->getCurrentLicenseDecisions($fileTreeBounds, $userId);

    ksort($licenseDecisions, SORT_STRING);

    if ($orderAscending)
    {
      $licenseDecisions = array_reverse($licenseDecisions);
    }

    $table = array();
    foreach ($licenseDecisions as $licenseShortName => $licenseDecision)
    {
      $licenseId = $licenseDecision['licenseId'];

      $types = array();
      $reportInfo = "";
      $comment = "";

      $entries = $licenseDecision['entries'];
      if (array_key_exists('direct', $entries))
      {
        $types[] = $entries['direct']['type'];
        $reportInfo = $entries['direct']['reportinfo'];
        $comment = $entries['direct']['comment'];
      }

      if (array_key_exists('agents', $entries))
      {
        foreach ($entries['agents'] as $agentEntry)
        {
          $matchTexts = array();
          foreach ($agentEntry['matches'] as $match)
          {
            $agentId = $match['agentId'];
            $matchId = $match['matchId'];
            $index = $match['index'];
            $matchText = "<a href=\"" . $uberUri . "&item=$uploadTreeId&agentId=$agentId&licenseId=$licenseId&highlightId=$matchId#highlight\">#$index</a>";
            if (array_key_exists('percentage', $match))
            {
              $matchText .= "(" . $match['percentage'] . " %)";
            }
            $matchTexts[] = $matchText;
          }

          $types[] = $agentEntry['name'] . ": " . implode(', ', $matchTexts);
        }
      }
      $licenseShortNameWithLink = $this->getLicenseFullTextLink($licenseShortName);
      $actionLink = "<a href=\"javascript:;\" onClick=\"removeLicense($uploadId, $uploadTreeId, $licenseId);\"><img src=\"images/icons/close_16.png\"></a>";

      $reportInfoField = $reportInfo;
      $commentField = $comment;

      $idArray = array($uploadTreeId, $licenseId);
      $id = implode(',', $idArray);
      $table[] = array('DT_RowId' => $id,
          '0' => $licenseShortNameWithLink,
          '1' => implode("<br/>", $types),
          '2' => $reportInfoField,
          '3' => $commentField,
          '4' => $actionLink);
    }
    return $table;
  }
}

$NewPlugin = new AjaxClearingView();