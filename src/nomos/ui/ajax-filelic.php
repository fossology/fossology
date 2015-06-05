<?php
/***********************************************************
 Copyright (C) 2009-2013 Hewlett-Packard Development Company, L.P.

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
 * \file ajax-filelic.php
 * \brief This plugin finds all the uploadtree_pk's in the first directory
 * level under a parent, that contain a given license. \n 
 * GET args: napk, lic, item \n 
 * item   is the parent uploadtree_pk \n 
 * napk   is the nomosagent_pk whos results you are looking for \n 
 * lic    is the shortname of the license \n 
 * ajax usage: \n 
 * http://...?mod=ajax_filelic&napk=123&item=123456&lic=FSF \n 
 *
 * \return the rf_shortname, and comma delimited string of uploadtree_pks: "FSF,123,456"
 */

define("TITLE_ajax_filelic", _("ajax find items by license"));

class ajax_filelic extends FO_Plugin
{
  var $Name       = "ajax_filelic";
  var $Title      = TITLE_ajax_filelic;
  var $Version    = "1.0";
  var $Dependency = array();
  var $DBaccess   = PLUGIN_DB_READ;
  var $NoHTML     = 1; /* This plugin needs no HTML content help */
  var $LoginFlag = 0;

  /**
   * @brief Display the loaded menu and plugins.
   */
  function Output()
  {
    $nomosagent_pk = GetParm("napk", PARM_INTEGER);
    $rf_shortname = GetParm("lic", PARM_RAW);
    $uploadtree_pk = GetParm("item", PARM_INTEGER);
    $uploadtree_tablename = GetParm("ut", PARM_RAW);

    $files = Level1WithLicense($nomosagent_pk, $rf_shortname, $uploadtree_pk, false, $uploadtree_tablename);
    $csv = (count($files) != 0) ? rawurlencode($rf_shortname) . implode(',', array_keys($files)) : '';
    return $csv;
  }

}
$NewPlugin = new ajax_filelic;
$NewPlugin->Initialize();
