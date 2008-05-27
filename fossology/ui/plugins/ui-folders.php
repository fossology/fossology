<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

class ui_folders extends FO_Plugin
  {
  var $Name       = "folders";
  var $Title      = "Show Folders";
  var $Version    = "1.0";
  // var $MenuList   = "Jobs::Folders (refresh)";
  var $MenuTarget = "treenav";
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_READ;
  var $NoMenu     = 1;

  /***********************************************************
   Output(): This function returns the scheduler status.
   ***********************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    $V="";
    global $Plugins;
    global $DB;
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	/* Load the logo image */
	$Uri = Traceback_uri();
	$V .= "<center><a href='/' target='_top'><img alt='FOSSology' title='FOSSology' src='${Uri}images/fossology-logo.gif' align=absmiddle border=0></a></center><br>\n";
	$V .= FolderListScript();
	$V .= "<small><center>";
	$V .= "<a href='javascript:Expand();'>Expand</a> |";
	$V .= "<a href='javascript:Collapse();'>Collapse</a> |";
	$V .= "<a href='" . Traceback() . "'>Refresh</a>";
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
