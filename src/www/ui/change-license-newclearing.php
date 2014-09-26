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
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\ClearingDecWithLicenses;
use Fossology\Lib\Util\ChangeLicenseUtility;
use Fossology\Lib\Util\LicenseOverviewPrinter;

define("TITLE_changeLicensNewclearing", _("Private: get new clearing information"));

class changeLicenseNewClearing extends FO_Plugin
{

  /** @var ChangeLicenseUtility */
  private $changeLicenseUtility;

  /** @var LicenseOverviewPrinter */
  private $licenseOverviewPrinter;

  /** @var UploadDao */
  private $uploadDao;

  /** @var ClearingDao; */
  private $clearingDao;

  function __construct()
  {
    $this->Name = "change-license-newclearing";
    $this->Title = TITLE_changeLicensNewclearing;
    $this->Version = "1.0";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->NoHTML = 1;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;

    parent::__construct();

    global $container;
    $this->uploadDao = $container->get('dao.upload');
    $this->clearingDao = $container->get('dao.clearing');
    $this->changeLicenseUtility = $container->get('utils.change_license_utility');
    $this->licenseOverviewPrinter = $container->get('utils.license_overview_printer');
  }

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }

    global $SysConf;
    $userId = $SysConf['auth']['UserId'];
    $uploadTreeId = $_POST['uploadTreeId'];
    $clearingDecWithLicences = $this->clearingDao->getFileClearings($uploadTreeId);

    /** after changing one license, purge all the report cache */
    ReportCachePurgeAll();

    header('Content-type: text/json');

    print(json_encode(array(
      'tableClearing' => $this->changeLicenseUtility->printClearingTableInnerHtml($clearingDecWithLicences, $userId),
      'recentLicenseClearing' => $this->licenseOverviewPrinter->createRecentLicenseClearing($clearingDecWithLicences))));
  } // Output()


}

$NewPlugin = new changeLicenseNewClearing;
$NewPlugin->Initialize();


