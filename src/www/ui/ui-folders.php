<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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
 ***********************************************************/

define("TITLE_ui_folders", _("Show Folders"));

class ui_folders extends FO_Plugin
{
  function __construct()
  {
    $this->Name       = "folders";
    $this->Title      = TITLE_ui_folders;
  //   $this->MenuList   = "Jobs::Folders (refresh)";
    $this->MenuTarget = "treenav";
    $this->Dependency = array();
    $this->DBaccess   = PLUGIN_DB_READ;
    $this->NoMenu     = 1;

    parent::__construct();
  }

  /**
   * \brief This function returns the FOSSology logo and
   * Folder Navigation bar
   */
  protected function htmlContent()
  {
    $V="";
    $Uri = Traceback_uri();
    $V .= "<center><a href='http://fossology.org' target='_top'><img alt='FOSSology' title='FOSSology' src='${Uri}images/fossology-logo.gif' align=absmiddle border=0></a></center><br>\n";
    $V .= FolderListScript();
    $V .= "<small><center>";
    $text = _("Expand");
    $V .= "<a href='javascript:Expand();'>$text</a> | ";
    $text = _("Collapse");
    $V .= "<a href='javascript:Collapse();'>$text</a> | ";
    $text = _("Refresh");
    $V .= "<a href='" . Traceback() . "'>$text</a>";
    $V .= "</center></small>";
    $V .= "<P>\n";

    $V .= "<form>\n";
    $V .= FolderListDiv(-1,0);
    $V .= "</form>\n";
  
    return $V;
  }
}
$NewPlugin = new ui_folders;
$NewPlugin->Initialize();
