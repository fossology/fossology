<?php
/*
 Copyright (C) 2018, Siemens AG

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
use Fossology\Lib\UI\Component\MicroMenu;

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

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $itemId = GetParm("item", PARM_INTEGER);
    $textFormat = $this->microMenu->getFormatParameter($itemId);
    $pageNumber = GetParm("page", PARM_INTEGER);
    $this->microMenu->addFormatMenuEntries($textFormat, $pageNumber);

    // For all other menus, permit coming back here.
    $uploadId = GetParm("upload", PARM_INTEGER);
    if (!empty($itemId) && !empty($uploadId)) {
      $menuText = "keyword";
      $tooltipText = "keyword Analysis";
      $menuPosition = 56;
      $URI = KeywordView::NAME . Traceback_parm_keep(array("show", "format", "page", "upload", "item"));
      $this->microMenu->insert(MicroMenu::TARGET_DEFAULT, $menuText, $menuPosition, $this->getName(), $URI, $tooltipText);
    }
    $licId = GetParm("lic", PARM_INTEGER);
    if (!empty($licId)) {
      $this->NoMenu = 1;
    }
  }
}

register_plugin(new KeywordView());
