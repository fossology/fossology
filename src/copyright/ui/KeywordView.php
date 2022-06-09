<?php
/*
 SPDX-FileCopyrightText: © 2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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
