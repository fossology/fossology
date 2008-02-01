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

class ui_fake extends Plugin
  {
  var $Name="fake_menu";
  var $Version="1.0";

  function PostInitialize()
    {
    global $Plugins;
    if ($this->State != PLUGIN_STATE_VALID) { return(0); } // don't run
    // Make sure dependencies are met
    foreach($Dependency as $key => $val)
      {
      $id = plugin_find_id($val);
      if ($id < 0) { Destroy(); return(0); } 
      } 
   
    // Add default menus (with no actions linked to plugins)
    // menu_insert("Tools::Browse",0,NULL,NULL);
    // menu_insert("Tools::Licenses",0,NULL,NULL);
    // menu_insert("Tools::Licenses::Histogram",0,NULL,NULL);
    // menu_insert("Tools::Licenses::Details",0,NULL,NULL);
    // menu_insert("Tools::Licenses::Histogram::1::2::3::4",0,NULL,NULL);

    // It worked, so mark this plugin as ready.
    $this->State = PLUGIN_STATE_READY;
    // Add this plugin to the menu
    if (!strcmp($this->MenuList,""))
      {
      menu_insert($this->MenuList,$this->MenuOrder,$this->MenuTarget,$this->Name);
      }
    return($this->State == PLUGIN_STATE_READY);
    }

  };
$NewPlugin = new ui_fake;
$NewPlugin->Initialize();

?>
