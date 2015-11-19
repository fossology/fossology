<?php
/*
 Copyright (C) 2014-2015, Siemens AG

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
use Fossology\Lib\Data\Highlight;

require_once('Xpview.php');

class KeywordView extends Xpview
{
  const NAME = 'keyword-view';

  function __construct()
  {
    $this->decisionTableName = "keyword_decision";
    $this->tableName = "keyword";
    $this->modBack = 'keyword-hist';
    $this->optionName = "skipFileKeyword";
    $this->ajaxAction = "setNextPrevKeyword";
    $this->skipOption = "noKeyword";
    $this->highlightTypeToStringMap = array(Highlight::KEYWORDOTHERS => 'Keyword');
    $this->typeToHighlightTypeMap = array('keyword' => Highlight::KEYWORDOTHERS);
    $this->xptext = 'Keyword';
    parent::__construct(self::NAME, array(
        self::TITLE => _("Keyword Analysis")
    ));
  }
}

register_plugin(new KeywordView());
