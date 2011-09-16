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

define("TITLE_core_debug_fileloc", _("Global Variables"));

class core_debug_fileloc extends FO_Plugin
{
  var $Name       = "debug-fileloc";
  var $Title      = TITLE_core_debug_fileloc;
  var $Version    = "1.0";
  var $MenuList   = "Help::Debug::Global Variables";
  var $DBaccess   = PLUGIN_DB_DEBUG;

  /**
   * \brief This is where we check for
   * changes to the full-debug setting.
   */
  function PostInitialize()
  {
    if ($this->State != PLUGIN_STATE_VALID) {
      return(0);
    } // don't re-run

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
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    global $BINDIR, $LIBDIR, $LIBEXECDIR, $INCLUDEDIR, $MAN1DIR,
    $AGENTDIR, $SYSCONFDIR, $WEBDIR, $PHPDIR, $PROJECTSTATEDIR,
    $PROJECT, $DATADIR, $VERSION, $SVN_REV;
    $varray = array("BINDIR", "LIBDIR", "LIBEXECDIR", "INCLUDEDIR", "MAN1DIR",
           "AGENTDIR", "SYSCONFDIR", "WEBDIR", "PHPDIR", "PROJECTSTATEDIR",
           "PROJECT", "DATADIR", "VERSION", "SVN_REV");

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
        $text = _(" Variable");
        $V .= "<table cellpadding=3><tr><th align=left>$text</th><th>&nbsp";
        foreach ($varray as $var)
        $V .= "<tr><td>$var</td><td>&nbsp;</td><td>". $$var ."</td></tr>";
         
        $V .= "</table>";
        break;
      case "Text":
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
$NewPlugin = new core_debug_fileloc;
$NewPlugin->Initialize();

?>
