<?php
/*
 Copyright (C) 2014-2015, Siemens AG
 Author: Johannes Najjar

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
   * @overwrite
   * @param int $uploadId
   * @param int $uploadTreeId
   * @param int $agentId
   * @return array
   */
  protected function additionalVars($uploadId, $uploadTreeId, $agentId)
  {
    if (empty($agentId))
    {
      $agentMap = $this->agentDao->getLatestAgentResultForUpload($uploadId,array('copyright'));
      $agentId = array_key_exists('copyright',$agentMap) ? $agentMap['copyright'] : 0;
    }
    
    $modCopyrightHist = plugin_find('copyright-hist');
    $filter = '';
    list($output, $tableVars) = $modCopyrightHist->getTableForSingleType('statement', _("Copyright"), $uploadId, $uploadTreeId, $filter, $agentId);

    $vars = array('statement'=>$tableVars,
        'content' => "$output\n",
        'script' => '<script>$(document).ready(function() { createTablestatement(); } );</script>');
    return $vars;
  }
  
}

register_plugin(new CopyrightView());
