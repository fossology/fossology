<?php
/*
 SPDX-FileCopyrightText: © 2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Agent\Copyright\UI;

use Fossology\Lib\Data\Highlight;
use Fossology\Lib\UI\Component\MicroMenu;

class IpraView extends Xpview
{
  const NAME = 'ipra-view';

  function __construct()
  {
    $this->decisionTableName = "ipra_decision";
    $this->tableName = "ipra";
    $this->modBack = 'ipra-hist';
    $this->optionName = "skipFileIpra";
    $this->ajaxAction = "setNextPrevIpra";
    $this->skipOption = "noIpra";
    $this->highlightTypeToStringMap = array(Highlight::IPRA => 'Patent Relevant');
    $this->typeToHighlightTypeMap = array('ipra' => Highlight::IPRA);
    $this->xptext = 'patent relevant';
    parent::__construct(self::NAME,array(
        self::TITLE => _("View patent relevant Analysis")
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
      $menuText = "IPRA";
      $tooltipraText = "Patent Relevant Analysis";
      $menuPosition = 56;
      $URI = IpraView::NAME . Traceback_parm_keep(array("show", "format", "page", "upload", "item"));
      $this->microMenu->insert(MicroMenu::TARGET_DEFAULT, $menuText, $menuPosition, $this->getName(), $URI, $tooltipraText);
    }
    $licId = GetParm("lic", PARM_INTEGER);
    if (!empty($licId)) {
      $this->NoMenu = 1;
    }
  }
}

register_plugin(new IpraView());
