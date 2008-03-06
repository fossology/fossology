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

class ui_basenav extends Plugin
  {
  var $Name       = "Default";
  var $Title      = "Welcome to FOSSology";
  var $Version    = "2.0";
  var $MenuList   = "";

  function Output()
    {
    $V="";
    if ($this->State != PLUGIN_STATE_READY) { return; }
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$V .= "This system analyzes open source projects.";
	$V .= "Currently, this tool allows you to view project contents, meta information, and discovered license analysis.";
	$V .= "<P>\n";
	$V .= "To begin, either browse uploaded files using the browse menu item, ";
	$V .= "or select an option from the menu.";
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
$NewPlugin = new ui_basenav;
$NewPlugin->Initialize();
?>
