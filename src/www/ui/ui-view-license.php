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

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\FileTreeBounds;
use Fossology\Lib\Util\LicenseOverviewPrinter;
use Fossology\Lib\View\HighlightProcessor;
use Fossology\Lib\View\HighlightRenderer;
use Fossology\Lib\View\LicenseProcessor;
use Fossology\Lib\View\LicenseRenderer;
use Monolog\Logger;

/**
 * \file ui_view_license.php
 *
 * \brief View License Scanner Results
 */

define("TITLE_ui_view_license", _("View License"));

class ui_view_license extends FO_Plugin
{
  /**
   * @var Logger
   */
  private $logger;

  /**
   * @var UploadDao
   */
  private $uploadDao;

  /**
   * @var LicenseDao
   */
  private $licenseDao;

  /**
   * @var HighlightDao
   */
  private $highlightDao;

  /**
   * @var HighlightProcessor
   */
  private $highlightProcessor;

  /**
   * @var HighlightRenderer
   */
  private $highlightRenderer;

  /**
   * @var LicenseProcessor
   */
  private $licenseProcessor;

  /**
   * @var LicenseRenderer
   */
  private $licenseRenderer;


  /**
   * @var LicenseOverviewPrinter
   */
  private $licenseOverviewPrinter;


  /**
   * @var ClearingDao;
   */
  private $clearingDao;

  /**
   * @var array colorMapping
   */
  var $colorMapping;

  function __construct()
  {
    $this->Name = "view-license";
    $this->Title = TITLE_ui_view_license;
    $this->Version = "1.0";
    $this->Dependency = array("view");
    $this->DBaccess = PLUGIN_DB_READ;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;

    parent::__construct();

    global $container;
    $this->logger = $container->get("logger");
    $this->uploadDao = $container->get("dao.upload");
    $this->licenseDao = $container->get("dao.license");
    $this->highlightDao = $container->get("dao.highlight");
    $this->clearingDao = $container->get("dao.clearing");

    $this->highlightProcessor = $container->get("view.highlight_processor");
    $this->highlightRenderer = $container->get("view.highlight_renderer");
    $this->licenseRenderer = $container->get("view.license_renderer");
    $this->licenseProcessor = $container->get("view.license_processor");
    $this->licenseOverviewPrinter = new LicenseOverviewPrinter($this->licenseDao, $this->uploadDao, $this->clearingDao, $this->highlightRenderer);
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $Lic = GetParm("lic", PARM_STRING);
    if (!empty($Lic))
    {
      $this->NoMenu = 1;
    }
  } // RegisterMenus()

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
   * This function is called when user output is
   * requested.  This function is responsible for content.
   * The $ToStdout flag is "1" if output should go to stdout, and
   * 0 if it should be returned as a string.  (Strings may be parsed
   * and used by other plugins.)
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
    $uploadId  = GetParm("upload", PARM_INTEGER);

    if (empty($uploadId))
    {
      return;
    }

    $ModBack = GetParm("modback", PARM_STRING);
    if (empty($ModBack))
    {
      $ModBack = "license";
    }

    $licenseId = GetParm("licenseId", PARM_INTEGER);
    $selectedAgentId = GetParm("agentId", PARM_INTEGER);
    $highlightId = GetParm("highlightId", PARM_INTEGER);


    $uploadTreeTableName = GetUploadtreeTableName($uploadId);

    $fileTreeBounds  = $this->uploadDao->getFileTreeBounds($uploadTreeId,$uploadTreeTableName);

    $highlights = $this->getSelectedHighlighting($fileTreeBounds, $licenseId, $selectedAgentId, $highlightId);

    $hasHighlights = count($highlights) > 0;
    $output = $this->createLicenseHeader($uploadTreeId, $selectedAgentId, $licenseId, $highlightId, $hasHighlights);

    $view->ShowView(NULL, $ModBack, 1, 1, $output, False, True, $highlights, !empty($licenseId));
  }// Output()


  /**
   * @param $hasDiff
   * @return string legend box
   */
  function legendBox($hasDiff)
  {
    return $this->licenseOverviewPrinter->legendBox($hasDiff);
  }

  /**
   * @param FileTreeBounds $fileTreeBounds
   * @param $licenseId
   * @param $selectedAgentId
   * @param $highlightId
   * @return array
   */
  private function getSelectedHighlighting( FileTreeBounds $fileTreeBounds, $licenseId, $selectedAgentId, $highlightId)
  {
    $highlightEntries = $this->highlightDao->getHighlightEntries($fileTreeBounds, $licenseId, $selectedAgentId, $highlightId);
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
      $output .= $this->licenseOverviewPrinter->createEditButton($fileTreeBounds->getUploadId(), $uploadTreeId, $foundNothing);

      $licenseFileMatches = $this->licenseDao->getFileLicenseMatches($fileTreeBounds);
      $licenseMatches = $this->licenseProcessor->extractLicenseMatches($licenseFileMatches);
      $output .= $this->licenseOverviewPrinter->createLicenseOverview($licenseMatches, $fileTreeBounds->getUploadId(), $uploadTreeId, $selectedAgentId, $licenseId, $highlightId, $hasHighlights);
    }
    return $output;
  }

}

$NewPlugin = new ui_view_license();
$NewPlugin->Initialize();
