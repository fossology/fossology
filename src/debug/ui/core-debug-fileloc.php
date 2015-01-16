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

define("TITLE_core_debug_fileloc", _("Global Variables"));

class core_debug_fileloc extends FO_Plugin
{
  function __construct()
  {
    $this->Name       = "debug-fileloc";
    $this->Title      = TITLE_core_debug_fileloc;
    $this->MenuList   = "Help::Debug::Global Variables";
    $this->DBaccess   = PLUGIN_DB_ADMIN;
    parent::__construct();
  }

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
  public function Output()
  {
    global $BINDIR, $LIBDIR, $LIBEXECDIR, $INCLUDEDIR, $LOGDIR,
    $SYSCONFDIR, $PROJECTSTATEDIR, $PROJECT, $VERSION, $SVN_REV;
    $varray = array("BINDIR", "LIBDIR", "LIBEXECDIR", "INCLUDEDIR", "LOGDIR",
           "SYSCONFDIR", "PROJECTSTATEDIR", "PROJECT", "VERSION", "SVN_REV");
    global $MenuList;
    $V = "";
    $text = _(" Variable");
    $var1 = _("memory_limit");
    $val1 = ini_get('memory_limit');
    $var2 = _("post_max_size");
    $val2 = ini_get('post_max_size');
    $var3 = _("upload_max_filesize");
    $val3 = ini_get('upload_max_filesize');

    $V .= "<table cellpadding=3><tr><th align=left>$text</th><th>&nbsp";
    foreach ($varray as $var)
    {
      $V .= "<tr><td>$var</td><td>&nbsp;</td><td>" . $$var . "</td></tr>";
    }
    $V .= "<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>";
    $V .= "<tr><td>$var1</td><td>&nbsp;</td><td>$val1</td></tr>";
    $V .= "<tr><td>$var2</td><td>&nbsp;</td><td>$val2</td></tr>";
    $V .= "<tr><td>$var3</td><td>&nbsp;</td><td>$val3</td></tr>";

    $V .= "</table>";
    return $V;
  }

}
$NewPlugin = new core_debug_fileloc;
$NewPlugin->Initialize();
