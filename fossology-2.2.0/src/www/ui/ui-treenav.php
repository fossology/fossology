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

class ui_treenav extends FO_Plugin
{
  var $Name       = "treenav";
  var $Version    = "1.0";
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
$NewPlugin = new ui_treenav;
$NewPlugin->Initialize();
?>
