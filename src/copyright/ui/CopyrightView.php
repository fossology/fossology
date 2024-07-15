<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG
 Author: Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Agent\Copyright\UI;

use Fossology\Lib\Data\Highlight;

class CopyrightView extends Xpview
{
  const NAME = 'copyright-view';

  function __construct()
  {
    $this->decisionTableName = "copyright_decision";
    $this->tableName = "copyright";
    $this->modBack = 'copyright-hist';
    $this->optionName = "skipFileCopyRight";
    $this->ajaxAction = "setNextPrevCopyRight";
    $this->skipOption = "noCopyright";
    $this->highlightTypeToStringMap = array(Highlight::COPYRIGHT => 'copyright remark',
        Highlight::URL => 'URL', Highlight::EMAIL => 'e-mail address',
        Highlight::AUTHOR => 'author or maintainer');
    $this->xptext = 'copyright/e-mail/URL';
    $this->typeToHighlightTypeMap = array(
        'statement' => Highlight::COPYRIGHT,
        'email' => Highlight::EMAIL,
        'url' => Highlight::URL,
        'author' => Highlight::AUTHOR);
    parent::__construct(self::NAME, array(
        self::TITLE => _("View Copyright/Email/Url Analysis")
    ));
  }

  /**
   * @copydoc Fossology::Agent::Copyright::UI::Xpview::additionalVars()
   * @see Fossology::Agent::Copyright::UI::Xpview::additionalVars()
   */
  protected function additionalVars($uploadId, $uploadTreeId, $agentId)
  {
    if (empty($agentId)) {
      $agentMap = $this->agentDao->getLatestAgentResultForUpload($uploadId,array('copyright', 'reso'));
      $agentId = array_key_exists('copyright',$agentMap) ? $agentMap['copyright'] : 0;
      if (array_key_exists('reso',$agentMap)) {
        $ResoagentId = $agentMap['reso'];
        $agentId .= "," . $ResoagentId;
      }
    }
    $typeDescriptionPairs = array(
      'statement' => _("FOSSology"),
      'scancode_statement' => _("ScanCode")
    );
    $tableVars = array();
    $output = array();
    $modCopyrightHist = plugin_find('copyright-hist');
    $filter = '';
    foreach ($typeDescriptionPairs as $type=>$description) {
      if ($type==="scancode_statement") {
        $agentId = LatestAgentpk($uploadId, 'scancode_ars');
        $this->agentName = "scancode";
      }
      list($out, $vars) = $modCopyrightHist->getTableForSingleType($type, $description, $uploadId, $uploadTreeId, $filter, $agentId);
      $tableVars[$type] = $vars;
      $output[] = $out;
    }

    list ($vCopyright, $vScancode)=$output;
    $vars = array('tables'=>$tableVars,
        'foss_content' => "$vCopyright\n",
        'scan_content' => "$vScancode\n",
        'script' => $this->createScriptBlock());
    return $vars;
  }

  protected function createScriptBlock()
  {
    return "

    var copyrightTabViewCookie = 'stickyCopyrightViewTab';

    $(document).ready(function() {
      tableCopyright =  createTablestatement();
      tableScancode =  createTablescancode_statement();
      $('#CopyrightViewTabs').tabs({
        active: ($.cookie(copyrightTabViewCookie) || 0),
        activate: function(e, ui){
          // Get active tab index and update cookie
          var idString = $(e.currentTarget).attr('id');
          idString = parseInt(idString.slice(-1)) - 1;
          $.cookie(copyrightTabViewCookie, idString);
        }
      });
    });
    ";
  }
}

register_plugin(new CopyrightView());
