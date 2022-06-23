<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG
 Author: Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Agent\Copyright\UI;

use Fossology\Lib\Data\Highlight;
use Fossology\Lib\UI\Component\MicroMenu;

class EccView extends Xpview
{
  const NAME = 'ecc-view';

  function __construct()
  {
    $this->decisionTableName = "ecc_decision";
    $this->tableName = "ecc";
    $this->modBack = 'ecc-hist';
    $this->optionName = "skipFileEcc";
    $this->ajaxAction = "setNextPrevEcc";
    $this->skipOption = "noEcc";
    $this->highlightTypeToStringMap = array(Highlight::ECC => 'Export Restriction');
    $this->typeToHighlightTypeMap = array('ecc' => Highlight::ECC);
    $this->xptext = 'export restriction';
    parent::__construct(self::NAME, array(
      self::TITLE => _("View Export Control and Customs Analysis")
    ));
  }

  /**
   * @copydoc Fossology::Agent::Copyright::UI::Xpview::RegisterMenus()
   * @see Fossology::Agent::Copyright::UI::Xpview::RegisterMenus()
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
      $menuText = "ECC";
      $tooltipText = "Export Control Classification";
      $menuPosition = 56;
      $URI = EccView::NAME . Traceback_parm_keep(array("show", "format", "page", "upload", "item"));
      $this->microMenu->insert(MicroMenu::TARGET_DEFAULT, $menuText, $menuPosition, $this->getName(), $URI, $tooltipText);
    }
    $licId = GetParm("lic", PARM_INTEGER);
    if (!empty($licId)) {
      $this->NoMenu = 1;
    }
  }
}

register_plugin(new EccView());