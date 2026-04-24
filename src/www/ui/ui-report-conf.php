<?php
/*
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Data\Package\ComponentType;

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
    "componentType" => "ri_component_type",
    "componentId" => "ri_component_id",
    "footerNote" => "ri_footer",
    "generalAssesment" => "ri_general_assesment",
    "gaAdditional" => "ri_ga_additional",
    "gaRisk" => "ri_ga_risk",
    "dependencyBinarySource" => "ri_depnotes",
    "exportRestrictionText" => "ri_exportnotes",
    "copyrightRestrictionText" => "ri_copyrightnotes"
  );

  /**
   * @var array $radioListUR
   */
  private $radioListUR = array(
    "nonCritical" => "critical",
    "critical" => "critical",
    "noDependency" => "dependencySourceBinary",
    "dependencySource" => "dependencySourceBinary",
    "dependencyBinary" => "dependencySourceBinary",
    "noExportRestriction" => "exportRestriction",
    "exportRestriction" => "exportRestriction",
    "noRestriction" => "restrictionForUse",
    "restrictionForUse" => "restrictionForUse"
  );

  /**
   * @var array $checkBoxListSPDX
   */
  private $checkBoxListSPDX = array(
    "spdxLicenseComment" => "spdxLicenseComment",
    "ignoreFilesWOInfo" => "ignoreFilesWOInfo",
    "osselotExport" => "osselotExport"
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
    $textAreaNoneStyle = ' style="display:none;overflow:auto;width:98%;height:80px;"';
    $textAreaStyle = ' style="overflow:auto;width:98%;height:80px;"';
    $vars['styleDependencyTA'] = $vars['styleExportTA'] = $vars['styleRestrictionTA'] = $textAreaStyle;
    if ($row['ri_depnotes'] == 'NA' || empty($row['ri_depnotes'])) {
       $vars['styleDependencyTA'] = $textAreaNoneStyle;
    }

    if ($row['ri_exportnotes'] == 'NA' || empty($row['ri_exportnotes'])) {
       $vars['styleExportTA'] = $textAreaNoneStyle;
    }

    if ($row['ri_copyrightnotes'] == 'NA' || empty($row['ri_copyrightnotes'])) {
       $vars['styleRestrictionTA'] = $textAreaNoneStyle;
    }

    if (!empty($row['ri_ga_checkbox_selection'])) {
      $listURCheckbox = explode(',', $row['ri_ga_checkbox_selection']);
      foreach (array_keys($this->radioListUR) as $key => $value) {
        $vars[$value] = $listURCheckbox[$key];
      }
    }

    if (!empty($row['ri_spdx_selection'])) {
      $listSPDXCheckbox = explode(',', $row['ri_spdx_selection']);
      foreach (array_keys($this->checkBoxListSPDX) as $key => $value) {
        $vars[$value] = $listSPDXCheckbox[$key];
      }
    } else {
      foreach (array_keys($this->checkBoxListSPDX) as $key) {
        $vars[$key] = 'unchecked';
      }
    }

    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);
    $allClearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $groupId);

    $tableRows = "";
    $excludedObligations = array();
    $excludedObligations = (array) json_decode($row['ri_excluded_obligations'], true);
    foreach ($this->getAllObligationsForGivenUploadId($groupId, $allClearingDecisions) as $obTopic => $obData) {
      $tableRows .= '<tr><td style="width:35%">'.$obTopic.'</td>';
      $tableRows .= '<td><textarea readonly="readonly" style="overflow:auto;width:98%;height:80px;">'.
                     $obData['text'].'</textarea></td><td>';
      foreach ($obData['license'] as $value) {
        if (!empty($excludedObligations[$obTopic]) && in_array($value, $excludedObligations[$obTopic])) {
          $tableRows .= '<input class="browse-upload-checkbox view-license-rc-size" type="checkbox" name="obLicenses['.urlencode($obTopic).'][]" value="'.$value.'" checked> '.$value.'<br />';
        } else {
          $tableRows .= '<input class="browse-upload-checkbox view-license-rc-size" type="checkbox" name="obLicenses['.urlencode($obTopic).'][]" value="'.$value.'"> '.$value.'<br />';
        }
      }
      $tableRows .= '</td></tr>';
    }
    $tableRowsUnifiedReport = "";
    $unifiedColumns = array();
    if (!empty($row['ri_unifiedcolumns'])) {
      $unifiedColumns = (array) json_decode($row['ri_unifiedcolumns'], true);
    } else {
      $unifiedColumns = UploadDao::UNIFIED_REPORT_HEADINGS;
    }
    foreach ($unifiedColumns as $name => $unifiedReportColumns) {
      foreach ($unifiedReportColumns as $columnName => $isenabled) {
        $tableRowsUnifiedReport .= '<tr>';
        $tableRowsUnifiedReport .= '<td><input class="form-control" type="text" style="width:95%" name="'.$name.'[]" value="'.$columnName.'"></td>';
        $checked = '';
        if ($isenabled) {
          $checked = 'checked';
        }
        $tableRowsUnifiedReport .= '<td style="vertical-align:middle"><input class="browse-upload-checkbox view-license-rc-size" type="checkbox" style="width:95%" name="'.$name.'[]" '.$checked.'></td>';
        $tableRowsUnifiedReport .= '</tr>';
      }
    }

    $tableRowsClixmlReport = "";
    $clixmlColumns = array();
    if (!empty($row['ri_clixmlcolumns'])) {
      $clixmlColumns = (array) json_decode($row['ri_clixmlcolumns'], true);
    } else {
      $clixmlColumns = UploadDao::CLIXML_REPORT_HEADINGS;
    }
    foreach ($clixmlColumns as $name => $clixmlReportColumns) {
      foreach ($clixmlReportColumns as $columnName => $isenabled) {
        $tableRowsClixmlReport .= '<tr>';
        $tableRowsClixmlReport .= '<td><input class="form-control" type="text" style="width:95%" name="'.$name.'[]" value="'.$columnName.'" readonly></td>';
        $checked = '';
        if ($isenabled) {
          $checked = 'checked';
        }
        $tableRowsClixmlReport .= '<td style="vertical-align:middle"><input class="browse-upload-checkbox view-license-rc-size" type="checkbox" style="width:95%" name="'.$name.'[]" '.$checked.'></td>';
        $tableRowsClixmlReport .= '</tr>';
      }
    }

    if (!empty($row['ri_globaldecision'])) {
      $vars['applyGlobal'] = "checked";
    }
    $vars['tableRows'] = $tableRows;
    $vars['tableRowsUnifiedReport'] = $tableRowsUnifiedReport;
    $vars['tableRowsClixmlReport'] = $tableRowsClixmlReport;
    $vars['ackRows'] = $this->buildAcknowledgementRows($uploadId, $allClearingDecisions);
    $vars['scriptBlock'] = $this->createScriptBlock();

    return $vars;
  }

  /**
   * @brief Get acknowledgements for cleared licenses grouped by text
   * @param array $clearingDecisions Pre-fetched clearing decisions
   * @return array ['ackText' => ['count' => int, 'licenses' => []]]
   */
  function getAllAcknowledgementsForUpload(array $clearingDecisions)
  {
    $ackData = array();
    foreach ($clearingDecisions as $clearingDecision) {
      if ($clearingDecision->getType() == DecisionTypes::IRRELEVANT) {
        continue;
      }
      $uploadTreeId = $clearingDecision->getUploadTreeId();
      foreach ($clearingDecision->getClearingLicenses() as $clearingLicense) {
        if ($clearingLicense->isRemoved()) {
          continue;
        }
        $ack = $clearingLicense->getAcknowledgement();
        if (empty(trim($ack))) {
          continue;
        }
        if (!isset($ackData[$ack])) {
          $ackData[$ack] = array('files' => array(), 'licenses' => array());
        }
        $ackData[$ack]['files'][$uploadTreeId] = true;
        $ackData[$ack]['licenses'][$clearingLicense->getShortName()] = true;
      }
    }
    $result = array();
    foreach ($ackData as $ack => $data) {
      $result[$ack] = array(
        'count' => count($data['files']),
        'licenses' => array_keys($data['licenses'])
      );
    }
    return $result;
  }

  /**
   * @brief Build HTML table rows for acknowledgements tab
   * @param int   $uploadId
   * @param array $clearingDecisions Pre-fetched clearing decisions
   * @return string HTML string of table rows
   */
  function buildAcknowledgementRows($uploadId, array $clearingDecisions)
  {
    $acknowledgements = $this->getAllAcknowledgementsForUpload($clearingDecisions);
    $rows = "";
    foreach ($acknowledgements as $ackText => $ackData) {
      $ackHtml = htmlspecialchars($ackText, ENT_QUOTES);
      $licensesHtml = htmlspecialchars(implode(', ', $ackData['licenses']));
      $rows .= '<tr>';
      $rows .= '<td style="width:5%;text-align:center">'
             . '<a href="#" class="ack-count-link" data-upload="' . $uploadId
             . '" data-ack="' . $ackHtml . '">' . $ackData['count'] . '</a></td>';
      $rows .= '<td style="width:60%"><textarea class="ack-text-area" readonly="readonly"'
             . ' data-original-ack="' . $ackHtml . '"'
             . ' style="overflow:auto;width:98%;height:60px;">'
             . $ackHtml . '</textarea></td>';
      $rows .= '<td style="width:25%">' . $licensesHtml . '</td>';
      $rows .= '<td style="width:10%;text-align:center">'
             . '<button type="button" class="btn btn-danger btn-sm delete-ack-btn"'
             . ' data-upload="' . $uploadId . '" data-ack="' . $ackHtml . '">'
             . 'Delete</button></td>';
      $rows .= '</tr>';
    }
    return $rows;
  }

  /**
   * @brief get all the obligations for cleared licenses
   * @param int   $groupId
   * @param array $clearingDecisions Pre-fetched clearing decisions
   * @return array with all obligations grouped by obligation topic
   */
  function getAllObligationsForGivenUploadId($groupId, array $clearingDecisions)
  {
    $allClearedLicenses = array();
    $licenseMap = new LicenseMap($this->dbManager, $groupId, LicenseMap::REPORT);
    foreach ($clearingDecisions as $clearingDecision) {
      if ($clearingDecision->getType() == DecisionTypes::IRRELEVANT) {
        continue;
      }
      foreach ($clearingDecision->getClearingLicenses() as $clearingLicense) {
        if ($clearingLicense->isRemoved()) {
          continue;
        }
        $allClearedLicenses[$licenseMap->getProjectedId($clearingLicense->getLicenseId())] = true;
      }
    }
    $uniqueLicenseIds = array_keys($allClearedLicenses);
    $allObligations = array_merge(
      $this->licenseDao->getLicenseObligations($uniqueLicenseIds) ?: array(),
      $this->licenseDao->getLicenseObligations($uniqueLicenseIds, true) ?: array()
    );
    $groupedObligations = array();
    foreach ($allObligations as $obligation) {
      $groupBy = $obligation['ob_topic'];
      $licenseName = LicenseRef::convertToSpdxId($obligation['rf_shortname'],
        $obligation['rf_spdx_id']);
      if (array_key_exists($groupBy, $groupedObligations)) {
        $groupedObligations[$groupBy]['license'][$licenseName] = true;
      } else {
        $groupedObligations[$groupBy] = array(
          "topic" => $obligation['ob_topic'],
          "text" => $obligation['ob_text'],
          "license" => array($licenseName => true)
        );
      }
    }
    foreach ($groupedObligations as &$ob) {
      $ob['license'] = array_keys($ob['license']);
    }
    return $groupedObligations;
  }

  /**
   * @param array $listParams
   * @return string
   */
  protected function getCheckBoxSelectionList($listParams)
  {
    foreach ($listParams as $listkey => $listValue) {
      $ret = GetParm($listValue, PARM_STRING);
      if ($ret != $listkey) {
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
      $applyGlobal = @$_POST["applyGlobal"];
      $applyGlobal = !empty($applyGlobal) ? 1 : 0;
      $parms = array();
      $obLicensesEncoded = @$_POST["obLicenses"];
      $obLicensesEncoded = !empty($obLicensesEncoded) ? $obLicensesEncoded : array();
      $obLicenses = array();
      array_walk($obLicensesEncoded,
        function (&$licArray, $obTopic) use (&$obLicenses) {
          $obLicenses[urldecode($obTopic)] = $licArray;
        }
      );
      $i = 1;
      $columns = "";
      foreach ($this->mapDBColumns as $key => $value) {
        $columns .= $value." = $".$i.", ";
        $parms[] = GetParm($key, PARM_RAW);
        $i++;
      }
      $parms[] = $this->getCheckBoxSelectionList($this->radioListUR);

      $unifiedReportColumnsForJson = array();
      foreach (UploadDao::UNIFIED_REPORT_HEADINGS as $columnName => $columnValue) {
        $columnResult = @$_POST[$columnName];
        $unifiedReportColumnsForJson[$columnName] = array($columnResult[0] => isset($columnResult[1]) ? $columnResult[1] : null);
      }
      $clixmlColumnsForJson = array();
      foreach (UploadDao::CLIXML_REPORT_HEADINGS as $columnName => $columnValue) {
        $columnResult = @$_POST[$columnName];
        $clixmlColumnsForJson[$columnName] = array($columnResult[0] => isset($columnResult[1]) ? $columnResult[1] : null);
      }
      $checkBoxUrPos = count($parms);
      $parms[] = $this->getCheckBoxSelectionList($this->checkBoxListSPDX);
      $checkBoxSpdxPos = count($parms);
      $parms[] = json_encode($obLicenses);
      $excludeObligationPos = count($parms);
      $parms[] = json_encode($unifiedReportColumnsForJson);
      $unifiedColumnsPos = count($parms);
      $parms[] = json_encode($clixmlColumnsForJson);
      $clixmlColumnsPos = count($parms);
      $parms[] = $applyGlobal;
      $applyGlobalPos = count($parms);
      $parms[] = $uploadId;
      $uploadIdPos = count($parms);

      $SQL = "UPDATE report_info SET $columns" .
               "ri_ga_checkbox_selection = $$checkBoxUrPos, " .
               "ri_spdx_selection = $$checkBoxSpdxPos, " .
               "ri_excluded_obligations = $$excludeObligationPos, " .
               "ri_unifiedcolumns = $$unifiedColumnsPos, " .
               "ri_clixmlcolumns = $$clixmlColumnsPos, " .
               "ri_globaldecision = $$applyGlobalPos " .
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
    $this->vars['typemap'] = [];
    foreach (ComponentType::TYPE_MAP as $key => $name) {
      if ($key == ComponentType::PACKAGEURL) {
        continue;
      }
      $this->vars['typemap'][] = ['key' => $key, 'name' => $name];
    }
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
          // Hide submit button on acknowledgements tab (AJAX-only tab)
          if (ui.newPanel.attr('id') === 'acknowledgementsConfTab') {
            $('#reportConfSubmit').hide();
          } else {
            $('#reportConfSubmit').show();
          }
        }
      });

      var activeIdx = $.cookie(reportTabCookie) || 0;
      if ($(\"#confTabs .ui-tabs-panel\").eq(activeIdx).attr('id') === 'acknowledgementsConfTab') {
        $('#reportConfSubmit').hide();
      }
      $(\"input[name='dependencySourceBinary']\").change(function(){
        var val = $(\"input[name='dependencySourceBinary']:checked\").val();
        if (val == 'noDependency') {
          $('#dependencyBinarySource').hide();
          $('#dependencyBinarySource').val('');
        } else {
          $('#dependencyBinarySource').css('display', 'block');
        }
      });
      $(\"input[name='exportRestriction']\").change(function(){
        var val = $(\"input[name='exportRestriction']:checked\").val();
        if (val == 'noExportRestriction') {
          $('#exportRestrictionText').hide();
          $('#exportRestrictionText').val('');
        } else {
          $('#exportRestrictionText').css('display', 'block');
        }
      });
      $(\"input[name='restrictionForUse']\").change(function(){
        var val = $(\"input[name='restrictionForUse']:checked\").val();
        if (val == 'noRestriction') {
          $('#copyrightRestrictionText').hide();
          $('#copyrightRestrictionText').val('');
        } else {
          $('#copyrightRestrictionText').css('display', 'block');
        }
      });
      
      $('#osselotExportCheckbox').change(function() {
        if ($(this).is(':checked')) {
          $('#spdxLicenseCommentCheckbox').prop('checked', true);
        }
      });
      $(\"[data-toggle='tooltip']\").tooltip();
      $('#spdxLicenseCommentCheckbox').change(function() {
        if (!this.checked) {
          $('#osselotExportCheckbox').prop('checked', false);
        }
      });

      var ackAjaxUrl = '?mod=ajax-acknowledgement-conf';

      $(document).on('click', '.ack-count-link', function(e) {
        e.preventDefault();
        var upload = $(this).data('upload');
        var ack    = $(this).data('ack');
        $('#ackFilesModalBody').html('<p>Loading...</p>');
        $('#ackFilesModalCount').text('');
        $('#ackFilesModal').modal('show');
        $.get(ackAjaxUrl, {action: 'getFiles', upload: upload, ack: ack},
          function(data) {
            if (!data || data.length === 0) {
              $('#ackFilesModalCount').text('');
              $('#ackFilesModalBody').html('<p>No files found.</p>');
              return;
            }
            $('#ackFilesModalCount').text('(' + data.length + ' files)');
            var html = '<table class=\"table table-condensed table-hover\" style=\"margin:0;\">'
                     + '<thead><tr><th>#</th><th>File</th></tr></thead><tbody>';
            $.each(data, function(i, file) {
              html += '<tr><td>' + (i + 1) + '</td>'
                    + '<td><a href=\"' + file.url + '\" target=\"_blank\">'
                    + $('<span/>').text(file.name).html()
                    + '</a></td></tr>';
            });
            html += '</tbody></table>';
            $('#ackFilesModalBody').html(html);
          }
        ).fail(function() {
          $('#ackFilesModalCount').text('');
          $('#ackFilesModalBody').html('<p>Error loading files.</p>');
        });
      });

      $(document).on('dblclick', '.ack-text-area', function() {
        if (!$(this).prop('readonly')) {
          return;
        }
        var ta = $(this);
        var td = ta.closest('td');
        ta.prop('readonly', false).css('border', '2px solid #337ab7').focus();
        td.append('<div class=\"ack-edit-actions\" style=\"margin-top:4px;\">'
          + '<button type=\"button\" class=\"btn btn-primary btn-xs ack-save-btn\">Save</button> '
          + '<button type=\"button\" class=\"btn btn-default btn-xs ack-cancel-btn\">Cancel</button>'
          + '</div>');
      });

      $(document).on('click', '.ack-save-btn', function() {
        var row    = $(this).closest('tr');
        var td     = $(this).closest('td');
        var ta     = td.find('.ack-text-area');
        var newAck = ta.val();
        var oldAck = ta.data('original-ack');
        if (newAck === oldAck) {
          ta.prop('readonly', true).css('border', '');
          td.find('.ack-edit-actions').remove();
          return;
        }
        var upload = row.find('.ack-count-link').data('upload');
        $.post(ackAjaxUrl,
          {action: 'updateAcknowledgement', upload: upload, oldAck: oldAck, newAck: newAck},
          function(data) {
            if (data && data.status === 'success') {
              $('#acknowledgementsConfTab tbody tr').each(function() {
                if ($(this).find('.ack-text-area').data('original-ack') === newAck
                    && this !== row[0]) {
                  $(this).remove();
                }
              });
              ta.val(newAck)
                .data('original-ack', newAck)
                .prop('readonly', true)
                .css('border', '');
              row.find('.ack-count-link').text(data.count).data('ack', newAck);
              row.find('.delete-ack-btn').data('ack', newAck);
              td.find('.ack-edit-actions').remove();
            } else {
              alert('Failed to update acknowledgement.');
            }
          }
        ).fail(function() {
          alert('Error communicating with server.');
        });
      });

      $(document).on('click', '.ack-cancel-btn', function() {
        var td = $(this).closest('td');
        var ta = td.find('.ack-text-area');
        ta.val(ta.data('original-ack'))
          .prop('readonly', true)
          .css('border', '');
        td.find('.ack-edit-actions').remove();
      });

      $(document).on('click', '.delete-ack-btn', function() {
        var upload = $(this).data('upload');
        var ack    = $(this).data('ack');
        if (!confirm('Are you sure you want to clear this acknowledgement from all files?')) {
          return;
        }
        var row = $(this).closest('tr');
        $.post(ackAjaxUrl,
          {action: 'deleteAcknowledgement', upload: upload, ack: ack},
          function(data) {
            if (data && data.status === 'success') {
              row.fadeOut(400, function() { row.remove(); });
            } else {
              alert('Failed to delete acknowledgement.');
            }
          }
        ).fail(function() {
          alert('Error communicating with server.');
        });
      });
    });
    ";
  }
}

$NewPlugin = new ui_report_conf();
$NewPlugin->Initialize();
