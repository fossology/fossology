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
  function __construct()
  {
    $this->Name       = "debug_flush_cache";
    $this->Title      = TITLE_debug_flush_cache;
    $this->MenuList   = "Help::Debug::Flush Cache";
    $this->DBaccess   = PLUGIN_DB_ADMIN;
    parent::__construct();
  }

  /**
   * \brief Generate output.
   */
  public function Output()
  {
    ReportCachePurgeAll();
    return _("All cached pages have been removed.");
  }

}
$NewPlugin = new debug_flush_cache;
$NewPlugin->Initialize();
