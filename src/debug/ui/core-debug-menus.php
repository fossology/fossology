<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.

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

define("TITLE_core_debug_menus", _("Debug Menus"));

class core_debug_menus extends FO_Plugin
{
  var $Name       = "debug-menus";
  var $Title      = TITLE_core_debug_menus;
  var $Version    = "1.0";
  var $MenuList   = "Help::Debug::Debug Menus";
  var $DBaccess   = PLUGIN_DB_ADMIN;

  /**
   * \brief This is where we check for
   * changes to the full-debug setting.
   */
  function PostInitialize()
  {
    if ($this->State != PLUGIN_STATE_VALID) {
      return(0);
    } // don't re-run
    // Make sure dependencies are met
    foreach($this->Dependency as $key => $val)
    {
      $id = plugin_find_id($val);
      if ($id < 0) {
        $this->Destroy(); return(0);
      }
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

  /**
   * \brief display the full menu as an ordered list.
   * This is recursive.
   */
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
      if (!empty($M->SubMenu)) {
        $this->Menu2HTML($M->SubMenu);
      }
    }
    print "</ol>\n";
  } // Menu2HTML()

  /**
   * \brief display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
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
          $text = _("Full debug ENABLED!");
          $text1 = _("Now view any page.");
          print "<b>$text</b> $text1<P>\n";
        }
        if ($FullMenuDebug == 1)
        {
          $text = _("Full debug disabled!");
          $text1 = _("Now view any page.");
          print "<b>$text</b> $text1<P>\n";
        }
        $text = _("This developer tool lists all items in the menu structure.");
        print "$text\n";
        $text = _("Since some menu inserts are conditional, not everything may appear here (the conditions may not lead to the insertion).");
        print "$text\n";
        $text = _("Fully-debugged menus show the full menu path and order number");
        $text1 = _("in the menu");
        print "$text <i>$text1</i>.\n";
        print "<ul>\n";
        $text = _("The full debugging is restricted to");
        $text1 = _("your");
        $text2 = _(" login session. (Nobody else will see it.)\n");
        print "<li>$text <b>$text1</b>$text2";
        $text = _("Full debugging shows the full menu path for each menu\n");
        print "<li>$text";
        $text = _("and the order is included in parenthesis.");
        print "$text\n";
        $text = _("However, menus that use HTML instead of text will");
        $text1 = _("not");
        $text2 = _("show the full path.\n");
        print "$text <i>$text1</i>$text2";
        $text = _("To disable full debugging, return here and unselect the option.\n");
        print "<li>$text";
        print "</ul>\n";
        print "<br>\n";
        print "<form method='post'>\n";
        if (@$_SESSION['fullmenudebug'] == 1)
        {
          print "<input type='hidden' name='fullmenu' value='1'>";
          $text = _("Disable Full Debug");
          print "<input type='submit' value='$text!'>";
        }
        else
        {
          print "<input type='hidden' name='fullmenu' value='2'>";
          $text = _("Enable Full Debug");
          print "<input type='submit' value='$text!'>";
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
    if (!$this->OutputToStdout) {
      return($V);
    }
    print($V);
    return;
  } // Output()


};
$NewPlugin = new core_debug_menus;
$NewPlugin->Initialize();

?>
