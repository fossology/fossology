<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

define("TITLE_debug_flush_cache", _("Flush Cache"));

/**
 * @class debug_flush_cache
 * @brief Plugin to flush all cached pages
 */
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
   * @brief Purge all cached pages
   * @copydoc FO_Plugin::Output()
   * @see FO_Plugin::Output()
   * @see ReportCachePurgeAll()
   */
  public function Output()
  {
    ReportCachePurgeAll();
    return _("All cached pages have been removed.");
  }

}
$NewPlugin = new debug_flush_cache;
$NewPlugin->Initialize();
