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

define("TITLE_debug_flush_cache", _("Flush Cache"));

class debug_flush_cache extends FO_Plugin
{
  var $Name       = "debug_flush_cache";
  var $Version    = "1.0";
  var $Title      = TITLE_debug_flush_cache;
  var $MenuList   = "Help::Debug::Flush Cache";
  var $Dependency = array();
  var $DBaccess   = PLUGIN_DB_ADMIN;

  /**
   * \brief Generate output.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V="";
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        ReportCachePurgeAll();
        $V .= _("All cached pages have been removed.\n");
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
$NewPlugin = new debug_flush_cache;
$NewPlugin->Initialize();
?>
