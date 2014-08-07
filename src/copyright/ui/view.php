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
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\View\HighlightRenderer;

/**
 * \file view.php
 * \brief View Copyright/Email/Url Analysis on an Analyzed file
 */

define("TITLE_copyright_view", _("View Copyright/Email/Url Analysis"));

class copyright_view extends FO_Plugin
{

  /**
   * @var UploadDao
   */
  private $uploadDao;

  /**
   * @var CopyrightDao
   */
  private $copyrightDao;

  /**
   * @var HighlightRenderer
   */
  private $highlightRenderer;

  function __construct()
  {
    $this->Name = "copyrightview";
    $this->Title = TITLE_copyright_view;
    $this->Version = "1.0";
    $this->Dependency = array("browse", "view");
    $this->DBaccess = PLUGIN_DB_READ;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;

    parent::__construct();

    global $container;
    $this->uploadDao = $container->get('dao.upload');
    $this->copyrightDao = $container->get('dao.copyright');
    $this->highlightRenderer = $container->get('view.highlight_renderer');
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("show", "format", "page", "upload", "item"));
    $Item = GetParm("item", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);
    if (!empty($Item) && !empty($Upload))
    {
      if (GetParm("mod", PARM_STRING) == $this->Name)
      {
        menu_insert("View::View Copyright/Email/Url", 1);
        menu_insert("View-Meta::View Copyright/Email/Url", 1);
      } else
      {
        $text = _("View Copyright/Email/Url info");
        menu_insert("View::View Copyright/Email/Url", 1, $URI, $text);
        menu_insert("View-Meta::View Copyright/Email/Url", 1, $URI, $text);
      }
    }
    $Lic = GetParm("lic", PARM_INTEGER);
    if (!empty($Lic))
    {
      $this->NoMenu = 1;
    }
  } // RegisterMenus()

  /**
   * \brief This function is called when user output is
   * requested.  This function is responsible for content. \n
   * The $ToStdout flag is "1" if output should go to stdout, and
   * 0 if it should be returned as a string.  (Strings may be parsed
   * and used by other plugins.)
   */
  function Output()
  {
    global $PG_CONN;
    if ($this->State != PLUGIN_STATE_READY)
    {
      return;
    }
    global $Plugins;
    /** @var ui_view $view */
    $view = & $Plugins[plugin_find_id("view")];
    $uploadTreeId = GetParm("item", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);

    $ModBack = GetParm("modback", PARM_STRING);
    if (empty($ModBack))
    {
      $ModBack = 'copyrighthist';
    }

    $highlights = $this->copyrightDao->getCopyrightHighlights($uploadTreeId);

    if (count($highlights) < 1)
    {
      $text = _("No copyright data is available for this file.");
      print $text;
      return;
    }

    $view->ShowView(NULL, $ModBack, 1, 1, $this->legendBox(false), true, true, $highlights);
    return;
  }

  /**
   * @return string rendered legend box
   */
  function legendBox()
  {
    $colorMapping = $this->highlightRenderer->colorMapping;

    $output = '<b>' . _("Legend") . ':</b><br/>';
    $output .= _("file text");
    foreach (array(Highlight::COPYRIGHT => 'copyright remark', Highlight::URL => 'URL', Highlight::EMAIL => 'eMail address')
             as $colorKey => $txt)
    {
      $output .= '<br/>' . $this->highlightRenderer->createStyle($colorKey, $txt, $colorMapping) . $txt . '</span>';
    }
    return '<div style="background-color:white; padding:2px; border:1px outset #222222; width:150px; position:fixed; right:5px; bottom:5px;">' . $output . '</div>';
  }
}


$NewPlugin = new copyright_view;
$NewPlugin->Initialize();
