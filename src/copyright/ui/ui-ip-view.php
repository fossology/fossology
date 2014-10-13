<?php
/***********************************************************
 * Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/
use Fossology\Lib\Dao\IPDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\View\HighlightRenderer;

/**
 * \file ui-cp-view.php
 * \brief View Copyright/Email/Url Analysis on an Analyzed file
 */

define("TITLE_ip_view", _("View patent Analysis"));

if (!class_exists('copyright_view'))
{
  require_once dirname(__FILE__).'/ui-cp-view.php';
}


class ip_view extends copyright_view
{
  /** @var IPDao */
  private $ipDao;
  
  function __construct()
  {
    parent::__construct();

    $this->Name = "ip-view";
    $this->Title = TITLE_ip_view;
    $this->Dependency = array("browse", "view");

    $this->vars['xptext'] = 'patent';
    global $container;
    $this->uploadDao = $container->get('dao.upload');
    $this->copyrightDao = $container->get('dao.ip');
    $this->highlightRenderer = $container->get('view.highlight_renderer');
    
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    // For this menu, I prefer having this in one place
    $text = _("View IP info");
    menu_insert("ViewIP::View", 35, $this->Name . Traceback_parm_keep(array("show", "format", "page", "upload", "item")), $text);

    $text = _("View file information");
    menu_insert("ViewIP::Info", 3, "view_info" . Traceback_parm_keep(array("upload", "item", "format")), $text);

    $text = _("Browse by buckets");
    menu_insert("ViewIP::Bucket Browser", 4, "bucketbrowser" . Traceback_parm_keep(array("format", "page", "upload", "item", "bp"), $text));
    $text = _("Copyright/Email/URL One-shot, real-time analysis");
    menu_insert("ViewIP::One-Shot Copyright/Email/URL",2, "agent_copyright_once", $text);
    $text = _("Nomos One-shot, real-time license analysis");
    menu_insert("ViewIP::One-Shot License", 2, "agent_nomos_once" . Traceback_parm_keep(array("format", "item")), $text);
    $text = _("Set the concluded licenses for this upload");
    menu_insert("ViewIP::Audit", 5, "view-license" . Traceback_parm_keep(array("upload", "item", "show")), $text);
    menu_insert("ViewIP::[BREAK]", 6);

    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("show", "format", "page", "upload", "item"));
    $Item = GetParm("item", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);
    if (!empty($Item) && !empty($Upload))
    {
      if (GetParm("mod", PARM_STRING) == $this->Name)
      {
        menu_insert("View::View IP", 1);
        menu_insert("View-Meta::View IP", 1);
      } else
      {
        $text = _("View patent info");
        menu_insert("View::IP", 1, $URI, $text);
        menu_insert("View-Meta::IP", 1, $URI, $text);
      }
    }
    $Lic = GetParm("lic", PARM_INTEGER);
    if (!empty($Lic))
    {
      $this->NoMenu = 1;
    }
  }

 
  
  protected function htmlContent()
  {
    $uploadTreeId = GetParm("item", PARM_INTEGER);
    if (empty($uploadTreeId))
    {
      return;
    }

    $uploadId = $this->uploadEntry['upload_fk'];
    $uploadTreeTableName = $this->uploadEntry['tablename'];
    $permission = GetUploadPerm($uploadId);
    $this->vars['micromenu'] = Dir2Browse('copyright-hist', $uploadTreeId, NULL, $showBox = 0, "ViewIP", -1, '', '', $uploadTreeTableName);

    $ModBack = GetParm("modback", PARM_STRING);
    if (empty($ModBack))
    {
      $ModBack = 'copyright-hist';
    }

    $highlights = $this->copyrightDao->getHighlights($uploadTreeId);

    if (count($highlights) < 1)
    {
      $this->vars['message'] = _("No data is available for this file.");
    }

    global $Plugins;
    /** @var ui_view $view */
    $view = &$Plugins[plugin_find_id("view")]; 
    $theView = $view->getView(NULL, $ModBack, $showHeader=0, "", $highlights, false, true);
    list($pageMenu, $textView)  = $theView;

    $this->vars['itemId'] = $uploadTreeId;
    $this->vars['uploadId'] = $uploadId;
    $this->vars['pageMenu'] = $pageMenu;
    $this->vars['textView'] = $textView;
    $this->vars['legendBox'] = $this->legendBox();
    $this->vars['uri'] = Traceback_uri() . "?mod=" . $this->Name;
    $this->vars['optionName'] = "skipFileCopyRight";
    $this->vars['ajaxAction'] = "setNextPrevCopyRight";
  }

  /**
   * @return string rendered legend box
   */
  function legendBox()
  {
    $colorMapping = $this->highlightRenderer->colorMapping;

    $output = '';
    $output .= _("file text");
    $output .= '<br/>' . $this->highlightRenderer->createStyle($colorKey=Highlight::IP, $txt='patent remark', $colorMapping) . $txt . '</span>';
    return $output;
  }
  
  function getTemplateName()
  {
    return 'ui-ip-view.html.twig';
  }
  
}

$NewPlugin = new ip_view;
$NewPlugin->Initialize();
