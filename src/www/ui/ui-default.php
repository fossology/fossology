<?php
/***********************************************************
 * Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

define("TITLE_ui_default", _("Welcome to FOSSology"));

class ui_default extends FO_Plugin
{
  var $Name = "Default";
  var $Title = TITLE_ui_default;
  var $Version = "2.0";
  var $MenuList = "";
  var $MenuOrder = 100;
  var $LoginFlag = 0;

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

    $this->vars['content'] = "<ul>
  <li><i18n>Upload files into the fossology repository.</i18n></li>
  <li><i18n>Unpack files (zip, tar, bz2, iso's, and many others) into its component files.</i18n></li>
  <li><i18n>Browse upload file trees.</i18n></li>
  <li><i18n>View file contents and meta data.</i18n></li>
  <li><i18n>Scan for software licenses.</i18n></li>
  <li><i18n>Scan for copyrights and other author information.</i18n></li>
  <li><i18n>View side-by-side license and bucket differences between file trees.</i18n></li>
  <li><i18n>Tag and attach notes to files.</i18n></li>
  <li><i18n>Report files based on your own custom classification scheme.</i18n></li>
</ul>";
  }
}

$NewPlugin = new ui_default;
$NewPlugin->Initialize();
