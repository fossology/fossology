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

  /******************************************
   PostInitialize(): This is where we check for
   changes to the full-debug setting.
   ******************************************/
  function PostInitialize()
    {
    if ($this->State != PLUGIN_STATE_VALID) { return(0); } // don't re-run
    // Make sure dependencies are met
    foreach($this->Dependency as $key => $val)
      {
      $id = plugin_find_id($val);
      if ($id < 0) { $this->Destroy(); return(0); }
      }

    $FullMenuDebug = GetParm("fullmenu",PARM_INTEGER);
    if ($FullMenuDebug == 2)
	{
	$_SESSION['fullmenudebug'] = 1;
	}
    if ($FullMenuDebug == 1)
	{
	$_SESSION['fullmenudebug'] = 0;
	}

    // It worked, so mark this plugin as ready.
    $this->State = PLUGIN_STATE_READY;
    // Add this plugin to the menu
    if ($this->MenuList !== "")
	{
	menu_insert("Main::" . $this->MenuList,$this->MenuOrder,$this->Name,$this->MenuTarget);
	}
    return(1);
    } // PostInitialize()

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
	$FullMenuDebug = GetParm("fullmenu",PARM_INTEGER);
	if ($FullMenuDebug == 2)
		{
		print "<b>Full debug ENABLED!</b> Now view any page.<P>\n";
		}
	if ($FullMenuDebug == 1)
		{
		print "<b>Full debug disabled!</b> Now view any page.<P>\n";
		}
	print "This developer tool lists all items in the menu structure.\n";
	print "Since some menu inserts are conditional, not everything may appear here (the conditions may not lead to the insertion).\n";
	print "Fully-debugged menus show the full menu path and order number <i>in the menu</i>.\n";
	print "<ul>\n";
	print "<li>The full debugging is restricted to <b>your</b> login session. (Nobody else will see it.)\n";
	print "<li>Full debugging shows the full menu path for each menu.\n";
	print "However, menus that use HTML instead of text will <i>not</i> show the full path.\n";
	print "<li>To disable full debugging, return here and unselect the option.\n";
	print "</ul>\n";
	print "<br>\n";
	print "<form method='post'>\n";
	if (@$_SESSION['fullmenudebug'] == 1)
	  {
	  print "<input type='hidden' name='fullmenu' value='1'>";
	  print "<input type='submit' value='Disable Full Debug!'>";
	  }
	else
	  {
	  print "<input type='hidden' name='fullmenu' value='2'>";
	  print "<input type='submit' value='Enable Full Debug!'>";
	  }
	print "</form>\n";
	print "<hr>";
	$this->Menu2HTML($MenuList);
	print "<hr>\n";
	print "<pre>";
	print htmlentities(print_r($MenuList,1));
	print "</pre>";
        break;
      case "Text":
	print_r($MenuList);
        break;
      default:
        break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
    } // Output()


  };
$NewPlugin = new core_debug_menus;
$NewPlugin->Initialize();

?>
