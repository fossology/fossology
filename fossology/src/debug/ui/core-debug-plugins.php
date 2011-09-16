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

define("TITLE_core_debug", _("Debug Plugins"));

class core_debug extends FO_Plugin
{
  var $Name       = "debug";
  var $Title      = TITLE_core_debug;
  var $Version    = "1.0";
  var $MenuList   = "Help::Debug::Debug Plugins";
  var $DBaccess   = PLUGIN_DB_DEBUG;

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V="";
    global $Plugins;
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $text = _("Plugin Summary");
        $V .= "<H2>$text</H2>";
        foreach ($Plugins as $key => $val)
        {
          $V .=  "$key : $val->Name (state=$val->State)<br>\n";
        }
        $text = _("Plugin State Details");
        $V .= "<H2>$text</H2>";
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
    if (!$this->OutputToStdout) {
      return($V);
    }
    print("$V");
    return;
  } // Output()


};
$NewPlugin = new core_debug;
$NewPlugin->Initialize();

?>
