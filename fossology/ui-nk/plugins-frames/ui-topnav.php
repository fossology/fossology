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

class ui_topnav extends Plugin
  {
  var $Type=PLUGIN_UI;
  var $Name="topnav";
  var $Version="1.0";
  var $MenuList="";
  var $Dependency=array("menus");

  function OutputDetail()
    {
    global $Plugins;
    $V="";
    if ($this->State != PLUGIN_STATE_READY) { return; }
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$Uri = Traceback_dir();
	$V .= "<body bgcolor='white'>\n";
	$V .= "<table border=0 cellpadding=0>\n";
	$V .= "  <tr>\n";
	$V .= "    <td rowspan=2>";
	$V .= "<a href='/' target='_top'><img src='${Uri}images/fossology-logo.gif' align=absmiddle border=0></a>";
	$V .= "</td>\n";
	$V .= "    <td><center><font size=+3>Software Repository Viewer</font></td></center>\n";
	$V .= "  </tr>\n";
	$V .= "  <tr>\n";
	$V .= "    <td>";
	$MenuId = plugin_find_id("menus");
	$Plugins[$MenuId]->OutputSet($this->OutputType,0);
	$V .= $Plugins[$MenuId]->OutputDetail();
	$Plugins[$MenuId]->OutputUnSet();
	$V .= "    </td>\n";
	$V .= "  </tr>\n";
	$V .= "</table>\n";
	$V .= "</body>\n";
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
    }

  };
$NewPlugin = new ui_topnav;
$NewPlugin->Initialize();
?>
