<?php
/***********************************************************
 Copyright (C) 2019 Siemens AG

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
 ***********************************************************/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\BusinessRules\LicenseMap;

class ui_report_conf extends FO_Plugin
{

  /** @var DbManager */
  private $dbManager;

  /** @var UploadDao $uploadDao
   * UploadDao object
   */
  private $uploadDao;

  /** @var UserDao $userDao
   * UserDao object */
  private $userDao;

  /** @var LicenseDao $licenseDao
   * LicenseDao object
   */
  private $licenseDao;

  /** @var ClearingDao $clearingDao
   * ClearingDao object
   */
  private $clearingDao;

  /**
   * @var mapDBColumns $mapDBColumns
   */
  private $mapDBColumns = array(
    "reviewedBy" => "ri_reviewed",
    "reportRel" => "ri_report_rel",
    "community" => "ri_community",
    "component" => "ri_component",
    "version" => "ri_version",
    "relDate" => "ri_release_date",
    "sw360Link" => "ri_sw360_link",
    "footerNote" => "ri_footer",
    "generalAssesment" => "ri_general_assesment",
    "gaAdditional" => "ri_ga_additional",
    "gaRisk" => "ri_ga_risk"
  );

  /**
   * @var checkBoxListUR $checkBoxListUR
   */
  private $checkBoxListUR = array(
    "nonCritical",
    "critical",
    "noDependency",
    "dependencySource",
    "dependencyBinary",
    "noExportRestriction",
    "exportRestriction",
    "noRestriction",
    "restrictionForUse"
  );

  /**
   * @var checkBoxListSPDX $checkBoxListSPDX
   */
  private $checkBoxListSPDX = array(
    "spdxLicenseComment",
    "ignoreFilesWOInfo"
  );


  function __construct()
  {
    $this->Name       = "report_conf";
    $this->Title      = _("Report Configuration");
    $this->Dependency = array("browse");
    $this->DBaccess   = PLUGIN_DB_READ;
    $this->LoginFlag  = 0;
    parent::__construct();
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
    $this->dbManager = $GLOBALS['container']->get('db.manager');
    $this->userDao = $GLOBALS['container']->get('dao.user');
    $this->clearingDao = $GLOBALS['container']->get('dao.clearing');
    $this->licenseDao = $GLOBALS['container']->get('dao.license');
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $tooltipText = _("Report Configuration");
    menu_insert("Browse-Pfile::Conf",5,$this->Name,$tooltipText);
    // For the Browse menu, permit switching between detail and summary.
    $Parm = Traceback_parm_keep(array("upload","item","format"));
    $URI = $this->Name . $Parm;

    $menuPosition = 60;
    $menuText = "Conf";
    if (GetParm("mod", PARM_STRING) == $this->Name) {
      menu_insert("View::[BREAK]", 61);
      menu_insert("View::[BREAK]", 50);
      menu_insert("View::{$menuText}", $menuPosition);
      menu_insert("View-Meta::[BREAK]", 61);
      menu_insert("View-Meta::[BREAK]", 50);
      menu_insert("View-Meta::{$menuText}", $menuPosition);

      menu_insert("Browse::Conf",-3);
    } else {
      $tooltipText = _("Report Configuration");
      menu_insert("View::[BREAK]", 61);
      menu_insert("View::[BREAK]", 50);
      menu_insert("View::{$menuText}", $menuPosition, $URI, $tooltipText);
      menu_insert("View-Meta::[BREAK]", 61);
      menu_insert("View-Meta::[BREAK]", 50);
      menu_insert("View-Meta::{$menuText}", $menuPosition, $URI, $tooltipText);

      menu_insert("Browse::Conf", -3, $URI, $tooltipText);
    }
  } // RegisterMenus()

  function allReportConfiguration($uploadId, $groupId)
  {
    $vars = [];
    $row = $this->uploadDao->getReportInfo($uploadId);
    foreach ($this->mapDBColumns as $key => $value) {
      $vars[$key] = $row[$value];
    }

    if (!empty($row['ri_ga_checkbox_selection'])) {
      $listURCheckbox = explode(',', $row['ri_ga_checkbox_selection']);
      foreach ($this->checkBoxListUR as $key => $value) {
        $vars[$value] = $listURCheckbox[$key];
      }
    }

    if (!empty($row['ri_spdx_selection'])) {
      $listSPDXCheckbox = explode(',', $row['ri_spdx_selection']);
      foreach ($this->checkBoxListSPDX as $key => $value) {
        $vars[$value] = $listSPDXCheckbox[$key];
      }
    }

    $tableRows = "";
    foreach ($this->getAllObligationsForGivenUploadId($uploadId, $groupId) as $obTopic => $obData) {
      $obLicCount = count($obData['license'])+1;
      $tableRows .= '<td rowspan = "'.$obLicCount.'">'.$obTopic.'</td>';
      $tableRows .= '<td rowspan = "'.$obLicCount.'">
        <textarea readonly="readonly" style="overflow:auto;width:98%;height:80px;">'.$obData['text'].'
        </textarea></td>';
      foreach ($obData['license'] as $key => $value) {
        $tableRows .= '<tr><td>';
        $tableRows .= '<input type="checkbox" name="obLicenses[]" value="'.$Value.'">'.$value;
        $tableRows .= '</tr></td>';
      }
    }
    $vars['tableRows'] = $tableRows;

    return $vars;
  }

  function getAllObligationsForGivenUploadId($uploadId, $groupId)
  {
    $clearedLicenses = array();
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);
    $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $groupId);
    $licenseMap = new LicenseMap($this->dbManager, $groupId, LicenseMap::REPORT);
    foreach ($clearingDecisions as $clearingDecision) {
      if ($clearingDecision->getType() == DecisionTypes::IRRELEVANT) {
        continue;
      }
      foreach ($clearingDecision->getClearingLicenses() as $clearingLicense) {
        if ($clearingLicense->isRemoved()) {
          continue;
        }
        $originLicenseId = $clearingLicense->getLicenseId();
        $clearedLicenses[] = $licenseMap->getProjectedId($originLicenseId);
      }
    }
    $obligationsForLicenses = $this->licenseDao->getLicenseObligations($clearedLicenses, 'obligation_map') ?: array();
    $obligationsForLicenseCandidates = $this->licenseDao->getLicenseObligations($clearedLicenses, 'obligation_candidate_map') ?: array();
    $allObligations = array_merge($obligationsForLicenses, $obligationsForLicenseCandidates);
    $groupedOb = array();
    foreach ($allObligations as $obligations) {
      $obTopic = $obligations['ob_topic'];
      $obText = $obligations['ob_text'];
      $licenseName = $obligations['rf_shortname'];
      $groupBy = $obTopic;
      if (array_key_exists($groupBy, $groupedOb)) {
        $currentLics = &$groupedOb[$groupBy]['license'];
        if (!in_array($licenseName, $currentLics)) {
          $currentLics[] = $licenseName;
        }
      } else {
        $singleOb = array(
         "topic" => $obTopic,
         "text" => $obText,
         "license" => array($licenseName)
        );
        $groupedOb[$groupBy] = $singleOb;
      }
    }
    return $groupedOb;
  }

  /**
   * @param array $checkBoxListParams
   * @return $cbSelectionList
   */
  protected function getCheckBoxSelectionList($checkBoxListParams)
  {
    foreach ($checkBoxListParams as $checkBoxListParam) {
      $ret = GetParm($checkBoxListParam, PARM_STRING);
      if (empty($ret)) {
        $cbList[] = "unchecked";
      } else {
        $cbList[] = "checked";
      }
    }
    $cbSelectionList = implode(",", $cbList);

    return $cbSelectionList;
  }

  public function Output()
  {
    $uploadId = GetParm("upload", PARM_INTEGER);
    $groupId = Auth::getGroupId();
    if (!$this->uploadDao->isAccessible($uploadId, $groupId)) {
      return;
    }

    $itemId = GetParm("item",PARM_INTEGER);
    $this->vars['micromenu'] = Dir2Browse("browse", $itemId, NULL, $showBox=0, "View-Meta");

    $submitReportConf = GetParm("submitReportConf", PARM_STRING);

    if (isset($submitReportConf)) {
      $parms = array();

      $i = 1;
      $columns = "";
      foreach ($this->mapDBColumns as $key => $value) {
        $columns .= $value." = $".$i.", ";
        $parms[] = GetParm($key, PARM_TEXT);
        $i++;
      }
      $parms[] = $this->getCheckBoxSelectionList($this->checkBoxListUR);
      $parms[] = $this->getCheckBoxSelectionList($this->checkBoxListSPDX);
      $parms[] = $uploadId;

      $SQL = "UPDATE report_info SET $columns" .
               "ri_ga_checkbox_selection = $12, ri_spdx_selection = $13" .
             "WHERE upload_fk = $14;";
      $this->dbManager->getSingleRow($SQL, $parms, __METHOD__ . "updateReportInfoData");
    }
echo '<pre>';
print_r($this->getAllObligationsForGivenUploadId($uploadId, $groupId)); 
echo '</pre>';
    $this->vars += $this->allReportConfiguration($uploadId, $groupId);
  }

  public function getTemplateName()
  {
    return "ui-report-conf.html.twig";
  }
}

$NewPlugin = new ui_report_conf();
$NewPlugin->Initialize();
