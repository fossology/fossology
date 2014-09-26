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
use Fossology\Lib\Dao\AgentsDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\FileTreeBounds;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\LicenseMatch;
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
  }



  /**
   * @param $uploadTreeId
   * @param $licenseId
   * @param $selectedAgentId
   * @param $highlightId
   * @return array
   */
  private function getSelectedHighlighting($uploadTreeId, $licenseId, $selectedAgentId, $highlightId)
  {
    $highlightEntries = $this->highlightDao->getHighlightEntries($uploadTreeId, $licenseId, $selectedAgentId, $highlightId);
    if ($selectedAgentId > 0)
    {
      $this->highlightProcessor->addReferenceTexts($highlightEntries);
    } else
    {
      $this->highlightProcessor->flattenHighlights($highlightEntries, array("K", "K "));
    }
    return $highlightEntries;
  }


  function OutputOpen()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return (0);
    }
    $uploadId = GetParm("upload", PARM_INTEGER);
    if (empty($uploadId))
    {
      return;
    }
    header('Content-type: text/json');
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
    return $output;
  }

  
  protected function jsonContent()
  {
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

    global $SysConf;
    $userId = $SysConf['auth']['UserId'];

    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $fileTreeBounds = $this->uploadDao->getFileTreeBounds($uploadTreeId, $uploadTreeTableName);
    $action = GetParm("do", PARM_STRING);

    if ($action)
    {
      switch ($action)
      {
        case "licenseDecisions":
          $aaData = $this->getCurrentLicenseDecisions($uploadId, $userId, $fileTreeBounds);
          return json_encode(
              array(
                  'sEcho' => intval($_GET['sEcho']),
                  'aaData' => $aaData,
                  'iTotalRecords' => count($aaData),
                  'iTotalDisplayRecords' => count($aaData)));

        case "removeLicense":
          $this->clearingDao->removeLicenseDecision($uploadTreeId, $userId, $licenseId, 1, false);
          return json_encode(array());
      }
    }
  }

  /**
   * @param $uploadId
   * @param $userId
   * @param FileTreeBounds $fileTreeBounds
   * @return array
   */
  protected function getCurrentLicenseDecisions($uploadId, $userId, FileTreeBounds $fileTreeBounds)
  {
    $licenseFileMatches = $this->licenseDao->getFileLicenseMatches($fileTreeBounds);
    $uploadTreeId = $fileTreeBounds->getUploadTreeId();

    $agentDetectedLicenses = array();
    foreach ($licenseFileMatches as $licenseMatch)
    {
      $licenseRef = $licenseMatch->getLicenseRef();
      $licenseShortName = $licenseRef->getShortName();
      if ($licenseShortName === "No_license_found")
      {
        continue;
      }
      $licenseIds[$licenseShortName] = $licenseRef->getId();
      $agentRef = $licenseMatch->getAgentRef();
      $agentName = $agentRef->getAgentName();
      $agentId = $agentRef->getAgentId();

      $agentDetectedLicenses[$licenseShortName][$agentName][$agentId][] = $licenseMatch->getPercent();
    }

    $agentsWithResults = array();
    foreach ($agentDetectedLicenses as $licenseShortName => $agentMap)
    {
      foreach ($agentMap as $agentName => $agentResultMap)
      {
        $agentsWithResults[$agentName] = $agentName;
      }
    }

    $agentLatestMap = $this->agentsDao->getLatestAgentResultForUpload($uploadId, $agentsWithResults);

    list($addedLicenses, $removedLicenses) = $this->clearingDao->getCurrentLicenseDecision($userId, $uploadTreeId);

    $licenseDecisions = array();
    foreach ($agentDetectedLicenses as $licenseShortName => $agentMap)
    {
      $selectedByUser = false;

      if (!array_key_exists($licenseShortName, $removedLicenses))
      {
        $agents = array();
        foreach ($agentMap as $agentName => $agentResultMap)
        {
          $agentEntry = $agentName . ": ";
          foreach ($agentResultMap as $agentId => $percentages)
          {
            if (!array_key_exists($agentName, $agentLatestMap))
            {
              continue;
            }
            if ($agentLatestMap[$agentName] === $agentId)
            {
              $percentageEntries = array();
              $index = 0;
              foreach ($percentages as $percentage)
              {
                $entry = "<a href=\"" . $this->vars['uri'] . "&item=$uploadTreeId&agentId=$agentId#highlight\">#" . ++$index . "</a>";
                if ($percentage)
                {
                  $entry .= "($percentage %)";
                }
                $percentageEntries[] = $entry;
              }
              $agentEntry .= implode(", ", $percentageEntries);
            }
          }
          $agents[] = $agentEntry;
        }
        $licenseId = $licenseIds[$licenseShortName];
        $licenseShortNameWithLink = "<a title=\"License Reference\" href=\"javascript:;\" onclick=\"javascript:window.open('/repo/?mod=popup-license&amp;lic=$licenseShortName','License Text','width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes');\">$licenseShortName</a>";
        $actionLink = "<a href=\"javascript:;\" onClick=\"removeLicense($uploadId, $uploadTreeId, $licenseId);\"><img src=\"images/icons/close_32.png\">" . ($selectedByUser ? "" : "auto") . " </a>";
        $licenseDecisions[] = array($licenseShortNameWithLink, implode("<br/>", $agents), "", "", $actionLink);
      }
    }
    return $licenseDecisions;
  }
}

$NewPlugin = new AjaxClearingView();