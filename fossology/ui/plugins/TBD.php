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

class tbd extends FO_Plugin
{
  var $Name       = "TBD";
  var $Title      = "TBD (don't look!)";
  var $Version    = "1.0";
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_WRITE;

  function RegisterMenus()
    {
    menu_insert("Main::Organize::Uploads::Edit Properties (TBD)");
    menu_insert("Main::Jobs::Analyze::Code Compare (TBD)");
    menu_insert("Main::Admin::Scheduler::Stop (TBD)");
    menu_insert("Main::Admin::Scheduler::Start (TBD)");
    menu_insert("Main::Admin::Scheduler::Kill Job (TBD)");
    menu_insert("Main::Admin::Scheduler::Update (TBD)");
    menu_insert("Main::Admin::Scheduler::Log (TBD)");
    menu_insert("Main::Admin::Database::View Table (TBD)");
    menu_insert("Main::Admin::Database::Stats (TBD)");
    }
};
$NewPlugin = new tbd;
$NewPlugin->Initialize();
?>
