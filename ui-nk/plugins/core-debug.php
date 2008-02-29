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

class core_debug extends Plugin
  {
  var $Name       = "debug";
  var $Version    = "1.0";
  var $MenuList   = "Help::Debug Plugins";
  var $DBaccess   = PLUGIN_DB_DEBUG;

  /***********************************************************
   Output(): Display the loaded menu and plugins.
   ***********************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;
    switch($this->OutputType)
      {
      case "XML":
        break;
      case "HTML":
	$V .= "<H1>Plugin State Summary</H1>";
	foreach ($Plugins as $key => $val)
	  {
	  $V .=  "$key : $val->Name (state=$val->State)<br>\n";
	  }
	$V .= "<H1>Plugin State Details</H1>";
	$V .= "<pre>";
	$V .= print_r($Plugins,1);
	$V .= "</pre>";
        break;
      case "Text":
	print_r($Plugins);
        break;
      default:
        break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
    } // Output()


  };
$NewPlugin = new core_debug;
$NewPlugin->Initialize();

?>
