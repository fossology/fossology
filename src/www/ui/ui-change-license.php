<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
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

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Util\ChangeLicenseUtility;
use Fossology\Lib\Util\LicenseOverviewPrinter;
use Fossology\Lib\View\HighlightRenderer;
use Fossology\Lib\View\LicenseProcessor;

/**
 * \file change-license.php
 * \brief change license of one file
 * \note if one file has multiple licenses, you only can change one each time, if you want to delete this
 * license, you can change it to No_license_found
 * \note this will change hopefully with this rewrite
 */

define("TITLE_change_license", _("Change License and Change History"));

class change_license extends FO_Plugin
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

  function __construct()
  {
    $this->Name = "change_license";
    $this->Title = TITLE_change_license;
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
    $this->changeLicenseUtility = new ChangeLicenseUtility();
    $highlightRenderer = new HighlightRenderer();
    $this->licenseOverviewPrinter = new LicenseOverviewPrinter($this->licenseDao, $this->uploadDao, $this->clearingDao, $highlightRenderer);
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $text = _("Change license");
    menu_insert("View::Audit", 35, $this->Name . Traceback_parm_keep(array("upload", "item", "show")), $text);
  }


  function cleanStrings($input)
  {
    return trim(pg_escape_string(urldecode($input)));
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

    $uploadId = GetParm("upload", PARM_INTEGER);
    $uploadTreeId = GetParm("item", PARM_INTEGER);

    $output = "";
    /* Get uploadtree table name */
    $uploadTreeTableName = GetUploadtreeTablename($uploadId);

    $output .= Dir2Browse('license', $uploadTreeId, NULL, 1, "View", -1, '', '', $uploadTreeTableName) . "<P />\n";

    $fileTreeBounds = $this->uploadDao->getFileTreeBounds($uploadTreeId, $uploadTreeTableName);

    $clearingDecWithLicenses = $this->clearingDao->getFileClearings($uploadTreeId);
    $output .= "<div id=\"recentLicenseClearing\" name=\"recentLicenseClearing\">";
    if (!empty($clearingDecWithLicenses))
    {
      $output .= $this->licenseOverviewPrinter->createRecentLicenseClearing($clearingDecWithLicenses);
    }
    $output .= "</div>";

    $licenseFileMatches = $this->licenseDao->getFileLicenseMatches($fileTreeBounds);
    $licenseMatches = $this->licenseProcessor->extractLicenseMatches($licenseFileMatches);
    $output .= $this->licenseOverviewPrinter->createLicenseOverview($licenseMatches, $fileTreeBounds->getUploadId(), $uploadTreeId, 0, 0, 0, false, true);
    /** check if the current user has the permission to change license */
    $permission = GetUploadPerm($uploadId);
    $text = _("Audit License");
    $output .= "<H2>$text</H2>\n";
    if ($permission >= PERM_WRITE)
    {
      $text = _("You do have write (or above permission) on this upload, thus you can change the license of this file.");
      $output .= "<b>$text</b>";

      $output .= $this->createChangeLicenseForm($uploadTreeId);

      $text = _("Clearing History:");
      $output .= "<h2>$text</h2>";
      $output .= $this->createClearingHistoryTable($uploadTreeId);
      $output .= $this->createJavaScriptBlock();

    } else
    {
      $text = _("Sorry, you do not have write (or above permission) on this upload, thus you cannot change the license of this file.");
      $output .= "<b>$text</b>";
    }

    $output .= "<br>";

    print $output;
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
  private function createChangeLicenseForm($uploadTreeId)
  {
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
    $output .= "<form name=\"licenseListSelect\">";
    $output .= " <table border=\"0\">	<tr>";

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
    $output .= $this->changeLicenseUtility->createDatabaseEnumSelect("scope", $clearingScopes, 3); //TODO extra class for scope and type
    $output .= "</td>";

    $output .= "<td colspan='2'>" . _("License decision type") . "<br/>";
    $clearingTypes = $this->clearingDao->getClearingTypes();
    $output .= $this->changeLicenseUtility->createDatabaseEnumSelect("type", $clearingTypes, 1);

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

    $text = _("Bulk recognition:");
    $output .= "<h2>$text</h2>";
    $text = _("reference text:");
    $output .= "$text:<br><textarea name=\"bulkRefText\" id=\"bulkRefText\" type=\"text\" cols=\"50\" rows=\"8\" maxlength=\"150\"></textarea>";
    $output .= $this->changeLicenseUtility->createListSelect("bulkLicense", $licenseRefs, false);
    $output .= "<button  type=\"button\" onclick='scheduleBulkScan()'>Run Bulk scan</button>";

    $output .= "<input name=\"licenseNumbersToBeSubmitted\" id=\"licenseNumbersToBeSubmitted\" type=\"hidden\" value=\"\" />\n";
    $output .= "<input name=\"uploadTreeId\" id=\"uploadTreeId\" type=\"hidden\" value=\"" . $uploadTreeId . "\" />\n </form>\n";

    return $output;

  }

  private function createJavaScriptBlock()
  {
    $output = "\n<script src=\"scripts/jquery-1.11.1.min.js\" type=\"text/javascript\"></script>\n";
    $output .= "\n<script src=\"scripts/change-license.js\" type=\"text/javascript\"></script>\n";
    return $output;
  }
}

$NewPlugin = new change_license;

