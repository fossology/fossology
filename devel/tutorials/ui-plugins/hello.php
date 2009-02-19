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

class ui_hello extends FO_Plugin
  {
  var $Name       = "hello";
  var $Title      = "Hello World Example";
  var $Version    = "1.0";
  var $MenuList   = "Help::Hello World";
  var $DBaccess   = PLUGIN_DB_NONE;
  var $LoginFlag  = 0;

  var $_Text="Hello World";

  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    switch($this->OutputType)
      {
      case "XML":
	$V .= "<text>$this->_Text</text>\n";
	break;
      case "HTML":
	$V .= "<b>$this->_Text</b>\n";
	break;
      case "Text":
	$V .= "$this->_Text\n";
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
    }

  };
$NewPlugin = new ui_hello;
$NewPlugin->Initialize();
?>
