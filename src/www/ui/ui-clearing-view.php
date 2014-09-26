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

define("TITLE_clearingView", _("Change concluded License "));

class ClearingView extends FO_Plugin
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
  private $licenseRenderer;
  /** @var array colorMapping */
  var $colorMapping;

  function __construct()
  {
    $this->Name = "view-license";
    $this->Title = TITLE_clearingView;
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;
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
   * \brief given a lic_shortname
   * retrieve the license text and display it.
   * @param $licenseShortname
   */
  function ViewLicenseText($licenseShortname)
  {
    $license = $this->licenseDao->getLicenseByShortName($licenseShortname);

    print(nl2br($this->licenseRenderer->renderFullText($license)));
  } // ViewLicenseText()


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
    $uploadId = GetParm("upload", PARM_INTEGER);
    if (empty($uploadId))
    {
      return;
    }

    $uploadTreeId = GetParm("item", PARM_INTEGER);
    if (empty($uploadTreeId))
    {
      $parent = $this->uploadDao->getUploadParent($uploadId);
      if (!isset($parent)) return;

      $uploadTreeId = $this->uploadDao->getNextItem($uploadId, $parent);

      header('Location: ' . Traceback_uri() . Traceback_parm_keep(array('mod', 'upload', 'show')) . "&item=$uploadTreeId");
    }

    $uploadTreeTableName = GetUploadtreeTableName($uploadId);
    $uploadEntry = $this->uploadDao->getUploadEntry($uploadTreeId, $uploadTreeTableName);
    if (Isdir($uploadEntry['ufile_mode']) || Iscontainer($uploadEntry['ufile_mode']))
    {
      $parent = $this->uploadDao->getUploadParent($uploadId);
      if (!isset($parent)) return;

      $uploadTreeId = $this->uploadDao->getNextItem($uploadId, $parent);

      header('Location: ?mod=' . $this->Name . Traceback_parm_keep(array("upload", "show")) . "&item=$uploadTreeId");
    }
    return parent::OutputOpen();
  }


  /**
   * \brief display the license changing page
   */
  protected function htmlContent()
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

    global $Plugins;
    /** @var $view ui_view */
    $view = &$Plugins[plugin_find_id("view")];

    $licenseId = GetParm("licenseId", PARM_INTEGER);
    $selectedAgentId = GetParm("agentId", PARM_INTEGER);
    $highlightId = GetParm("highlightId", PARM_INTEGER);
    $ModBack = GetParm("modback", PARM_STRING);

    global $SysConf;
    $userId = $SysConf['auth']['UserId'];

    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $fileTreeBounds = $this->uploadDao->getFileTreeBounds($uploadTreeId, $uploadTreeTableName);
    $ajaxMethod = GetParm("ajaxMethod", PARM_STRING);

    $this->vars['uri'] = Traceback_uri() . "?mod=" . $this->Name . Traceback_parm_keep(array('upload', 'folder'));

    if ($ajaxMethod)
    {
      $this->OutputToStdout = true;
      header('Content-type: text/json');

      switch ($ajaxMethod)
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

    if (empty($ModBack))
    {
      $ModBack = "license";
    }
    $highlights = $this->getSelectedHighlighting($uploadTreeId, $licenseId, $selectedAgentId, $highlightId);

    $hasHighlights = count($highlights) > 0;

    /* Get uploadtree table name */

    $this->vars['previousItem'] = $this->uploadDao->getPreviousItem($uploadId, $uploadTreeId);
    $this->vars['nextItem'] = $this->uploadDao->getNextItem($uploadId, $uploadTreeId);

    $permission = GetUploadPerm($uploadId);
    $licenseInformation = "";

    $this->vars['micromenu'] = Dir2Browse('license', $uploadTreeId, NULL, $showBox = 0, "ChangeLicense", -1, '', '', $uploadTreeTableName);

    $output = '';
    /* @var Fossology\Lib\Dao\FileTreeBounds */

    $licenseDecisionMap = array();
    $licenseIds = array();
    if (!$fileTreeBounds->containsFiles())
    {
      $clearingDecWithLicenses = $this->clearingDao->getFileClearings($uploadTreeId);
      $extractedLicenseBulkMatches = $this->licenseProcessor->extractBulkLicenseMatches($clearingDecWithLicenses);
      $output .= $this->licenseOverviewPrinter->createBulkOverview($extractedLicenseBulkMatches, $fileTreeBounds->getUploadId(), $uploadTreeId, $selectedAgentId, $licenseId, $highlightId, $hasHighlights);

      $licenseFileMatches = $this->licenseDao->getFileLicenseMatches($fileTreeBounds);
      $licenseMatches = $this->licenseProcessor->extractLicenseMatches($licenseFileMatches);

      foreach ($licenseFileMatches as $licenseMatch)
      {
        /** @var $licenseMatch LicenseMatch */
        $licenseRef = $licenseMatch->getLicenseRef();
        $licenseShortName = $licenseRef->getShortName();
        $licenseId = $licenseRef->getId();
        $agentRef = $licenseMatch->getAgentRef();
        $agentName = $agentRef->getAgentName();
        $agentId = $agentRef->getAgentId();

        $licenseIds[$licenseShortName] = $licenseId;
        $licenseDecisionMap[$licenseShortName][$agentName][$agentId][] = $licenseMatch->getPercent();
      }


      if ($permission >= PERM_WRITE)
      {
        $this->vars = array_merge($this->vars, $this->changeLicenseUtility->createChangeLicenseForm($uploadTreeId));
        $this->vars = array_merge($this->vars, $this->changeLicenseUtility->createBulkForm($uploadTreeId));

      } else
      {
        $this->vars['auditDenied'] = true;
      }

    }
    $licenseInformation .= $output;

    $dataTableConfig =
        '{  "bServerSide": true,
       "sAjaxSource": "?mod=view-license&ajaxMethod=licenseDecisions",
       "fnServerData": function ( sSource, aoData, fnCallback ) {
            aoData.push( { "name": "upload" , "value" : "' . $uploadId . '" } );
            aoData.push( { "name": "item" , "value" : "' . $uploadTreeId . '" } );
            $.getJSON( sSource, aoData, fnCallback ).fail( function() {
              if (confirm("You are not logged in. Go to login page?"))
                window.location.href="' . Traceback_uri() . '?mod=auth";
            });
          },
      "iDisplayLength": 25,
      "bProcessing": true,
      "bStateSave": true,
      "bRetrieve": true,
      "bPaginate": false,
    }';

    $script = "<script>
              function createLicenseDecisionTable() {
                    var otable = $('#licenseDecisionsTable').dataTable(" . $dataTableConfig . ");
                    return otable;
                }

            </script>";

    $this->vars['script'] = $script;


    $clearingHistory = array();
    if ($permission >= PERM_WRITE)
    {
      $clearingDecWithLicenses = $this->clearingDao->getFileClearings($uploadTreeId);
      $clearingHistory = $this->changeLicenseUtility->getClearingHistory($clearingDecWithLicenses, $userId);
    }

    list($pageMenu, $textView) = $view->getView(NULL, $ModBack, 0, "", $highlights, false, true);

    $this->vars['itemId'] = $uploadTreeId;
    $this->vars['path'] = $output;
    $this->vars['pageMenu'] = $pageMenu;
    $this->vars['textView'] = $textView;
    $this->vars['legendBox'] = $this->licenseOverviewPrinter->legendBox($selectedAgentId > 0 && $licenseId > 0);
    $this->vars['licenseDecisionsHeaders'] = array('License', 'Source', 'Text for report', 'Comment', 'Action');
    $this->vars['clearingTypes'] = $this->clearingDao->getClearingDecisionTypeMap();
    $this->vars['selectedClearingType'] = 'Identified';

    $this->vars['licenseInformation'] = $licenseInformation;
    $this->vars['clearingHistory'] = $clearingHistory;
  }

  public function getTemplateName()
  {
    return "view_license.html";
  }

  /*
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $text = _("Set the concluded licenses for this upload");
    menu_insert("Browse-Pfile::Clearing", 0, $this->Name, $text);
    menu_insert("ChangeLicense::View", 5, "view-license" . Traceback_parm_keep(array("show", "format", "page", "upload", "item")), $text);
    menu_insert("View::Audit", 35, $this->Name . Traceback_parm_keep(array("upload", "item", "show")), $text);
    $text = _("View file information");
    menu_insert("ChangeLicense::Info", 1, "view_info" . Traceback_parm_keep(array("upload", "item", "format")), $text);
    $text = _("View Copyright/Email/Url info");
    menu_insert("ChangeLicense::Copyright/Email/Url", 1, "copyrightview" . Traceback_parm_keep(array("show", "page", "upload", "item")), $text);
    $text = _("Browse by buckets");
    menu_insert("ChangeLicense::Bucket Browser", 1, "bucketbrowser" . Traceback_parm_keep(array("format", "page", "upload", "item", "bp"), $text));
    $text = _("Copyright/Email/URL One-shot, real-time analysis");
    menu_insert("ChangeLicense::One-Shot Copyright/Email/URL", 3, "agent_copyright_once", $text);
    $text = _("Nomos One-shot, real-time license analysis");
    menu_insert("ChangeLicense::One-Shot License", 3, "agent_nomos_once" . Traceback_parm_keep(array("format", "item")), $text);

    menu_insert("ChangeLicense::[BREAK]", 4);

    return 0;
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
  } // RegisterMenus()
}

$NewPlugin = new ClearingView;