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
  var $Name       = "folders";
  var $Title      = TITLE_ui_folders;
  var $Version    = "1.0";
  // var $MenuList   = "Jobs::Folders (refresh)";
  var $MenuTarget = "treenav";
  var $Dependency = array();
  var $DBaccess   = PLUGIN_DB_READ;
  var $NoMenu     = 1;

  /**
   * \brief This function returns the FOSSology logo and
   * Folder Navigation bar
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    $V="";
    global $Plugins;
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        /* Load the logo image */
        $Uri = Traceback_uri();
        $V .= "<center><a href='http://fossology.org' target='_top'><img alt='FOSSology' title='FOSSology' src='${Uri}images/fossology-logo.gif' align=absmiddle border=0></a></center><br>\n";
        $V .= FolderListScript();
        $V .= "<small><center>";
        $text = _("Expand");
        $V .= "<a href='javascript:Expand();'>$text</a> |";
        $text = _("Collapse");
        $V .= "<a href='javascript:Collapse();'>$text</a> |";
        $text = _("Refresh");
        $V .= "<a href='" . Traceback() . "'>$text</a>";
        $V .= "</center></small>";
        $V .= "<P>\n";

        /* Display the tree */
        $V .= "<form>\n";
        $V .= FolderListDiv(-1,0);
        $V .= "</form>\n";
        break;
    case "Text":
      break;
    default:
      break;
  }
  if (!$this->OutputToStdout) { return($V); }
  print "$V";
  return;
}

  };
  $NewPlugin = new ui_folders;
  $NewPlugin->Initialize();

  ?>
