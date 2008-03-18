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

class core_debug_menus extends FO_Plugin
  {
  var $Name       = "debug-menus";
  var $Title      = "Debug Menus";
  var $Version    = "1.0";
  var $MenuList   = "Help::Debug::Debug Menus";
  var $DBaccess   = PLUGIN_DB_DEBUG;

  /***********************************************************
   Menu2HTML(): Display the full menu as an ordered list.
   This is recursive.
   ***********************************************************/
  function Menu2HTML(&$Menu)
    {
    print "<ol>\n";
    foreach($Menu as $M)
      {
      print "<li>" . htmlentities($M->Name);
      print " (" . htmlentities($M->Order);
      print " -- " . htmlentities($M->URI);
      print " @ " . htmlentities($M->Target);
      print ")\n";
      if (!empty($M->SubMenu)) { $this->Menu2HTML($M->SubMenu); }
      }
    print "</ol>\n";
    } // Menu2HTML()

  /***********************************************************
   Output(): Display the loaded menu and plugins.
   ***********************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $MenuList;
    switch($this->OutputType)
      {
      case "XML":
        break;
      case "HTML":
	$this->Menu2HTML($MenuList);
	$V .= "<hr>\n";
	$V .= "<pre>";
	$V .= print_r($MenuList,1);
	$V .= "</pre>";
        break;
      case "Text":
	print_r($MenuList);
        break;
      default:
        break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
    } // Output()


  };
$NewPlugin = new core_debug_menus;
$NewPlugin->Initialize();

?>
