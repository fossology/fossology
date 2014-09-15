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
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DatabaseEnum;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Util\ChangeLicenseUtility;
use Fossology\Lib\Util\LicenseOverviewPrinter;
use Fossology\Lib\View\HighlightProcessor;
use Fossology\Lib\View\HighlightRenderer;
use Fossology\Lib\View\LicenseProcessor;
use Fossology\Lib\View\LicenseRenderer;
use Monolog\Logger;

define("TITLE_clearingView", _("Change concluded License "));

class ClearingView extends FO_Plugin
{
  /**
   * @var UploadDao
   */
  private $uploadDao;
  /**
   * @var LicenseDao
   */
  private $licenseDao;
  /**
   * @var ClearingDao;
   */
  private $clearingDao;
  /**
   * @var LicenseProcessor
   */
  private $licenseProcessor;
  /**
   * @var ChangeLicenseUtility
   */
  private $changeLicenseUtility;
  /**
   * @var LicenseOverviewPrinter
   */
  private $licenseOverviewPrinter;

  /**
   * @var Logger
   */
  private $logger;

  /**
   * @var HighlightDao
   */
  private $highlightDao;

  /**
   * @var HighlightProcessor
   */
  private $highlightProcessor;

  /**
   * @var LicenseRenderer
   */
  private $licenseRenderer;

  /**
   * @var array colorMapping
   */
  var $colorMapping;

  function __construct()
  {
    $this->Name = "view-license";
    $this->Title = TITLE_clearingView;
    $this->Version = "1.0";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;
    parent::__construct();

    global $container;
    $this->licenseDao = $container->get('dao.license');
    $this->uploadDao = $container->get('dao.upload');
    $this->clearingDao = $container->get('dao.clearing');
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
   * @param $selectedAgentId
   * @param $licenseId
   * @param $highlightId
   * @param $hasHighlights
   * @return string
   */
  private function createLicenseHeader($uploadTreeId, $selectedAgentId, $licenseId, $highlightId, $hasHighlights)
  {
    $output = "";
    $fileTreeBounds = $this->uploadDao->getFileTreeBounds($uploadTreeId);

    if (!$fileTreeBounds->containsFiles())
    {
      $clearingDecWithLicenses = $this->clearingDao->getFileClearings($uploadTreeId);
      list($outputTMP, $foundNothing) = $this->licenseOverviewPrinter->createWrappedRecentLicenseClearing($clearingDecWithLicenses);
      $output .= $outputTMP;

      $licenseFileMatches = $this->licenseDao->getFileLicenseMatches($fileTreeBounds);
      $licenseMatches = $this->licenseProcessor->extractLicenseMatches($licenseFileMatches);
      $output .= $this->licenseOverviewPrinter->createLicenseOverview($licenseMatches, $fileTreeBounds->getUploadId(), $uploadTreeId, $selectedAgentId, $licenseId, $highlightId, $hasHighlights);
    }
    return $output;
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

  /**
   * @param $uploadTreeId
   * @return LicenseRef[]
   */
  private function getAgentSuggestedLicenses($uploadTreeId)
  {
    $fileTreeBounds = $this->uploadDao->getFileTreeBounds($uploadTreeId, "uploadtree");
    $licenses = $this->licenseDao->getFileLicenseMatches($fileTreeBounds);
    $licenseList = array();

    foreach ($licenses as $licenseMatch)
    {
      $licenseList[] = $licenseMatch->getLicenseRef();

    }
    return $licenseList;
  }


  /**
   * @param $uploadTreeId
   * @return array of clearingHistory
   */
  private function createClearingHistoryTable($uploadTreeId)
  {
    global $SysConf;
    $user_pk = $SysConf['auth']['UserId'];
    $tableName = "clearingHistoryTable";
    $clearingDecWithLicenses = $this->clearingDao->getFileClearings($uploadTreeId);


    return $this->changeLicenseUtility->printClearingTable($tableName, $clearingDecWithLicenses, $user_pk);
  }

  /**
   * @param $uploadTreeId
   * @return string
   * creates two licenseListSelects and buttons to transfer licenses and two text boxes
   */
  private function createChangeLicenseForm($uploadTreeId) {
    $licenseRefs = $this->licenseDao->getLicenseRefs();

    $clearingDecWithLicenses = $this->clearingDao->getFileClearings($uploadTreeId);

    $preSelectedLicenses = null;
    if (!empty($clearingDecWithLicenses))
    {
      $filteredFileClearings = $this->clearingDao->newestEditedLicenseSelector->extractGoodClearingDecisionsPerFileID($clearingDecWithLicenses, true);
      if (!empty ($filteredFileClearings))
      {
        $preSelectedLicenses = reset($filteredFileClearings)->getLicenses();
      }
    }

    if ($preSelectedLicenses === null)
    {
      $preSelectedLicenses = $this->getAgentSuggestedLicenses($uploadTreeId);
    }

    $this->changeLicenseUtility->filterLists($licenseRefs, $preSelectedLicenses);

    $output = "";

    $output .= "<div class=\"modal\" id=\"userModal\" hidden>";
    $output .= "<form name=\"licenseListSelect\">";
    $output .= " <table border=\"0\"> <tr>";
    $text = _("Available licenses:");
    $output .= "<td><p>$text<br>";
    $output .= $this->changeLicenseUtility->createListSelect("licenseLeft", $licenseRefs);
    $output .= "</p></td>";

    $output .= "<td align=\"center\" valign=\"middle\">";
    $output .= $this->changeLicenseUtility->createLicenseSwitchButtons();
    $output .= "</td>";

    $text = _("Selected licenses:");
    $output .= "<td><p>$text<br>";
    $output .= $this->changeLicenseUtility->createListSelect("licenseRight", $preSelectedLicenses);
    $output .= "</p></td>";

    $output .= "<td>";
    $text = _("Comment (private)");
    $output .= "$text:<br><textarea name=\"comment\" id=\"comment\" type=\"text\" cols=\"50\" rows=\"8\" maxlength=\"150\"></textarea>";
    $text = _("Remark (public)");
    $output .= "<p>$text:<br><textarea name=\"remark\" id=\"remark\"   type=\"text\"  cols=\"50\" rows=\"10\" maxlength=\"150\"></textarea></p>";
    $output .= "</td>";


    $output .= "</tr>";
    $output .= "<tr><td colspan='2'>";
    $output .= "" . _("License decision scope") . "<br/>";
    $clearingScopes = $this->clearingDao->getClearingScopes();
    $output .= DatabaseEnum::createDatabaseEnumSelect("scope", $clearingScopes, 3);
    $output .= "</td>";

    $output .= "<td colspan='2'>" . _("License decision type") . "<br/>";
    $clearingTypes = $this->clearingDao->getClearingTypes();
    $output .= DatabaseEnum::createDatabaseEnumSelect("type", $clearingTypes, 1);

    $output .= "</td></tr>";
    $output .= "<tr><td>&nbsp;</td></tr>";
    $output .= "<tr><td colspan='2'>";
    $output .= "<button  type=\"button\" autofocus  onclick='performPostRequest()'>Submit</button>";
    $output .= "</td>";
    $output .= "<td colspan='2'>";
    $output .= "<button  type=\"button\" autofocus  onclick='performNoLicensePostRequest()'>No License contained</button>";
    $output .= "</td>";
    $output .= "</tr>";
    $output .= "<tr><td>&nbsp;</td></tr></table>";
    $output .= "<input name=\"licenseNumbersToBeSubmitted\" id=\"licenseNumbersToBeSubmitted\" type=\"hidden\" value=\"\" />\n";
    $output .= "<input name=\"uploadTreeId\" id=\"uploadTreeId\" type=\"hidden\" value=\"" . $uploadTreeId . "\" />\n </form>\n";
    $output .= "</div>";

    return $output;
  }

  private function createBulkForm($uploadTreeId) {
    $output = "";
    $allLicenseRefs = $this->licenseDao->getLicenseRefs();
    $output .= "<div class=\"modal\" id=\"bulkModal\" hidden>";
    $output .= "<form name=\"bulkForm\">";
    $text = _("Bulk recognition");
    $output .= "<h2>$text</h2>";
    $output .= "<select name=\"bulkRemoving\" id=\"bulkRemoving\">";
    $output .= "<option value=\"f\">Add license</option>";
    $output .= "<option value=\"t\">Remove license</option>";
    $output .= "</select>";
    $output .= $this->changeLicenseUtility->createListSelect("bulkLicense", $allLicenseRefs, false, 1);
    $text = _("reference text");
    $output .= "<br>$text:<br><textarea name=\"bulkRefText\" id=\"bulkRefText\" type=\"text\" cols=\"80\" rows=\"12\"></textarea><br>";
    $output .= "<br><button type=\"button\" onclick='scheduleBulkScan()'>Schedule Bulk scan</button>";
    $output .= "<br><span id=\"bulkIdResult\" name=\"bulkIdResult\" hidden></span>";
    $output .= "<br><span id=\"bulkJobResult\" name=\"bulkJobResult\" hidden>a bulk job has completed</span>";
    $output .= "</div>";
    $output .= "<input name=\"uploadTreeId\" id=\"uploadTreeId\" type=\"hidden\" value=\"" . $uploadTreeId . "\" />\n </form>\n";

    return $output;
  }


  private function createClearingButtons(){

    $uploadId = GetParm("upload", PARM_INTEGER);
    $uploadTreeId = GetParm("item", PARM_INTEGER);

    $text = _("Audit License");
    $output = "<h3>$text</h3>\n";

    /** check if the current user has the permission to change license */
    $permission = GetUploadPerm($uploadId);
    if ($permission >= PERM_WRITE)
    {
      $text = _("You do have write (or above permission) on this upload, thus you can change the license of this file.");
      $output .= "<b>$text</b>";

      $output .= $this->createChangeLicenseForm($uploadTreeId);
      $output .= $this->createBulkForm($uploadTreeId);

      $output .= "<br><button type=\"button\" onclick='openUserModal()'>User Decision</button>";
      $output .= "<br><button type=\"button\" onclick='openBulkModal()'>Bulk Recognition</button>";

      $text = _("Clearing History:");
      $output .= "<h3>$text</h3>";
      $output .= $this->createClearingHistoryTable($uploadTreeId);

    } else
    {
      $text = _("Sorry, you do not have write (or above) permission on this upload, thus you cannot change the license of this file.");
      $output .= "<b>$text</b>";
    }

    $output .= "<br>";

    return $output;
  }


  /**
   * \brief display the license changing page
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return;
    }
    global $Plugins;
    /**
     * @var $view ui_view
     */
    $view = & $Plugins[plugin_find_id("view")];

    $licenseShortname = GetParm("lic", PARM_TEXT);
    if (!empty($licenseShortname)) // display the detailed license text of one license
    {
      $this->ViewLicenseText($licenseShortname);
      return;
    }
    $uploadTreeId = GetParm("item", PARM_INTEGER);
    if (empty($uploadTreeId))
    {
      return;
    }
    $uploadId = GetParm("upload", PARM_INTEGER);

    if (empty($uploadId))
    {
      return;
    }


    $licenseId = GetParm("licenseId", PARM_INTEGER);
    $selectedAgentId = GetParm("agentId", PARM_INTEGER);
    $highlightId = GetParm("highlightId", PARM_INTEGER);
    $ModBack = GetParm("modback", PARM_STRING);
    if (empty($ModBack))
    {
      $ModBack = "license";
    }
    $highlights = $this->getSelectedHighlighting($uploadTreeId, $licenseId, $selectedAgentId, $highlightId);

    $hasHighlights = count($highlights) > 0;

    $output = "";
    /* Get uploadtree table name */
    $uploadTreeTableName = GetUploadtreeTablename($uploadId);

    $output .= Dir2Browse('license', $uploadTreeId, NULL, 1, "ChangeLicense", -1, '', '', $uploadTreeTableName) . "\n";


    $header= $this->createLicenseHeader($uploadTreeId, $selectedAgentId, $licenseId, $highlightId, $hasHighlights);
    $header .= $this->createClearingButtons();
    list($pageMenu,$text) = $view->getView(NULL, $ModBack, 0, "", $highlights, false, true);

    $legendBox = $this->licenseOverviewPrinter->legendBox($selectedAgentId > 0 && $licenseId > 0);


    $buttons = "<button class=\"legendHider\">"._("Hide Legend")."</button><button class=\"legendShower\">"._("Show Legend")."</button>";

    $output .= "<table width='100%' border='0' padding='0px'><tr><td  padding='0px' style='position:relative'><div class='centered'>$pageMenu $buttons</div>
      <div class='boxnew'>$text</div>$legendBox</td><td class='headerBox'><div>$header</div></td></tr></table>";

    $output .= $this->createJavaScriptBlock();
    print $output;
  }


  private function createJavaScriptBlock()
  {
    $output = "\n<script src=\"scripts/jquery-1.11.1.min.js\" type=\"text/javascript\"></script>\n";
    $output .= "\n<script src=\"scripts/jquery.plainmodal.min.js\" type=\"text/javascript\"></script>\n";
    $output .= "\n<script src=\"scripts/job-queue-poll.js\" type=\"text/javascript\"></script>\n";
    $output .= "\n<script src=\"scripts/change-license.js\" type=\"text/javascript\"></script>\n";
      $output .= "\n<script src=\"scripts/tools.js\" type=\"text/javascript\"></script>\n";
    return $output;
  }

}
$NewPlugin = new ClearingView;