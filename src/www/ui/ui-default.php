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

define("TITLE_ui_default", _("Welcome to FOSSology"));

class ui_default extends FO_Plugin
{
  var $Name       = "Default";
  var $Title      = TITLE_ui_default;
  var $Version    = "2.0";
  var $MenuList   = "";
  var $MenuOrder  = 100;
  var $LoginFlag  = 0;

  function RegisterMenus()
  {
    menu_insert("Main::Home", $this->MenuOrder, "Default", NULL, "_top");
  }

  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return;
    }
    $V = "";
    if ($this->OutputType == "HTML")
    {
      global $renderer;
      $V = $renderer->renderTemplate("default");
    }
    if (!$this->OutputToStdout)
    {
      return $V;
    }
    print($V);
  }
}
$NewPlugin = new ui_default;
$NewPlugin->Initialize();
