<?php
/***********************************************************
 Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.

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

/**
 * \file ajax-tags.php
 * \brief This plugin is used to list all uploads associated
 * with a folder.  This is NOT intended to be a user-UI
 * plugin.
 * This is intended as an active plugin to provide support
 * data to the UI.
 */

define("TITLE_ajax_tags", _("List Tags"));

class ajax_tags extends FO_Plugin
{
  var $Name       = "tag_get";
  var $Title      = TITLE_ajax_tags;
  var $Version    = "1.0";
  var $Dependency = array();
  var $DBaccess   = PLUGIN_DB_READ;
  var $NoHTML     = 1; /* This plugin needs no HTML content help */

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $Item = GetParm("uploadtree_pk",PARM_INTEGER);
        $List = GetAllTags($Item);
        foreach($List as $L)
        {
          $V .= $L['tag_name'] . ",";
        }
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
  } // Output()


};
$NewPlugin = new ajax_tags;
$NewPlugin->Initialize();

?>
