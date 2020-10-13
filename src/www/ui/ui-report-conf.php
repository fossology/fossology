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
    "department" => "ri_department",
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

  /**
   * @brief list all the options for Report Configuration
   * @param int $uploadId
   * @param int $groupId
   * @return array with all obligations grouped by obligation topic
   */
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
    $excludedObligations = array();
    $excludedObligations = (array) json_decode($row['ri_excluded_obligations'], true);
    foreach ($this->getAllObligationsForGivenUploadId($uploadId, $groupId) as $obTopic => $obData) {
      $tableRows .= '<tr><td style="width:35%">'.$obTopic.'</td>';
      $tableRows .= '<td><textarea readonly="readonly" style="overflow:auto;width:98%;height:80px;">'.
                     $obData['text'].'</textarea></td><td>';
      foreach ($obData['license'] as $value) {
        if (!empty($excludedObligations[$obTopic]) && in_array($value, $excludedObligations[$obTopic])) {
          $tableRows .= '<input type="checkbox" name="obLicenses['.$obTopic.'][]" value="'.$value.'" checked> '.$value.'<br />';
        } else {
          $tableRows .= '<input type="checkbox" name="obLicenses['.$obTopic.'][]" value="'.$value.'"> '.$value.'<br />';
        }
      }
      $tableRows .= '</td></tr>';
    }
    $vars['tableRows'] = $tableRows;
    $vars['scriptBlock'] = $this->createScriptBlock();

    return $vars;
  }

  /**
   * @brief get all the obgligations for cleared licenses
   * @param int $uploadId
   * @param int $groupId
   * @return array with all obligations grouped by obligation topic
   */
  function getAllObligationsForGivenUploadId($uploadId, $groupId)
  {
    $allClearedLicenses = array();
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);
    $allClearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $groupId);
    $licenseMap = new LicenseMap($this->dbManager, $groupId, LicenseMap::REPORT);
    foreach ($allClearingDecisions as $clearingDecisions) {
      if ($clearingDecisions->getType() == DecisionTypes::IRRELEVANT) {
        continue;
      }
      foreach ($clearingDecisions->getClearingLicenses() as $eachClearingLicense) {
        if ($eachClearingLicense->isRemoved()) {
          continue;
        }
        $getLicenseId = $eachClearingLicense->getLicenseId();
        $allClearedLicenses[] = $licenseMap->getProjectedId($getLicenseId);
      }
    }
    $obligationsForLicenses = $this->licenseDao->getLicenseObligations($allClearedLicenses) ?: array();
    $obligationsForLicenseCandidates = $this->licenseDao->getLicenseObligations($allClearedLicenses, true) ?: array();
    $allObligations = array_merge($obligationsForLicenses, $obligationsForLicenseCandidates);
    $groupedObligations = array();
    foreach ($allObligations as $obligations) {
      $groupBy = $obligations['ob_topic'];
      if (array_key_exists($groupBy, $groupedObligations)) {
        $currentLicenses = &$groupedObligations[$groupBy]['license'];
        if (!in_array($obligations['rf_shortname'], $currentLicenses)) {
          $currentLicenses[] = $obligations['rf_shortname'];
        }
      } else {
        $groupedObligations[$groupBy] = array(
         "topic" => $obligations['ob_topic'],
         "text" => $obligations['ob_text'],
         "license" => array($obligations['rf_shortname'])
        );
      }
    }
    return $groupedObligations;
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
    $userId = Auth::getUserId();
    if (!$this->uploadDao->isAccessible($uploadId, $groupId)) {
      return;
    }

    $itemId = GetParm("item",PARM_INTEGER);
    $this->vars['micromenu'] = Dir2Browse("browse", $itemId, NULL, $showBox=0, "View-Meta");
    $this->vars['globalClearingAvailable'] = Auth::isClearingAdmin();

    $submitReportConf = GetParm("submitReportConf", PARM_STRING);

    if (isset($submitReportConf)) {
      $parms = array();
      $obLicenses = @$_POST["obLicenses"];
      $i = 1;
      $columns = "";
      foreach ($this->mapDBColumns as $key => $value) {
        $columns .= $value." = $".$i.", ";
        $parms[] = GetParm($key, PARM_TEXT);
        $i++;
      }
      $parms[] = $this->getCheckBoxSelectionList($this->checkBoxListUR);
      $checkBoxUrPos = count($parms);
      $parms[] = $this->getCheckBoxSelectionList($this->checkBoxListSPDX);
      $checkBoxSpdxPos = count($parms);
      $parms[] = json_encode($obLicenses);
      $excludeObligationPos = count($parms);
      $parms[] = $uploadId;
      $uploadIdPos = count($parms);

      $SQL = "UPDATE report_info SET $columns" .
               "ri_ga_checkbox_selection = $$checkBoxUrPos, " .
               "ri_spdx_selection = $$checkBoxSpdxPos, " .
               "ri_excluded_obligations = $$excludeObligationPos" .
               "WHERE upload_fk = $$uploadIdPos;";
      $this->dbManager->getSingleRow($SQL, $parms,
        __METHOD__ . "updateReportInfoData");

      if (@$_POST['markGlobal']) {
        $upload = $this->uploadDao->getUpload($uploadId);
        $uploadName = $upload->getFilename();
        $jobId = JobAddJob($userId, $groupId, $uploadName, $uploadId);
        /** @var agent_fodecider $deciderPlugin */
        $deciderPlugin = plugin_find("agent_deciderjob");
        $conflictStrategyId = "global";
        $errorMsg = "";
        $deciderPlugin->AgentAdd($jobId, $uploadId, $errorMsg, array(), $conflictStrategyId);
        $schedulerMsg = empty(GetRunnableJobList()) ? _("Is the scheduler running? ") : '';
        $url = Traceback_uri() . "?mod=showjobs&upload=$uploadId";
        $text = _("Your jobs have been added to job queue.");
        $linkText = _("View Jobs");
        $this->vars['message'] = "$schedulerMsg" . "$text <a href=\"$url\">$linkText</a>";
      }
    }
    $this->vars += $this->allReportConfiguration($uploadId, $groupId);
  }

  public function getTemplateName()
  {
    return "ui-report-conf.html.twig";
  }

  /**
   * @brief Create Script block for conf
   * @return string JavaScript block
   */
  protected function createScriptBlock()
  {
    return "

    var reportTabCookie = 'stickyReportTab';

    $(document).ready(function() {
      $(\"#confTabs\").tabs({
        active: ($.cookie(reportTabCookie) || 0),
        activate: function(e, ui){
          // Get active tab index and update cookie
          var idString = $(e.currentTarget).attr('id');
          idString = parseInt(idString.slice(-1)) - 1;
          $.cookie(reportTabCookie, idString);
        }
      });
    });
    ";
  }
}

$NewPlugin = new ui_report_conf();
$NewPlugin->Initialize();
