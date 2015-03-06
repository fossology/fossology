<?php
/***********************************************************
 Copyright (C) 2010-2012 Hewlett-Packard Development Company, L.P.

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
  function __construct()
  {
    $this->Name       = "tag_get";
    $this->Title      = TITLE_ajax_tags;
    $this->DBaccess   = PLUGIN_DB_READ;
    $this->OutputType = 'Text'; /* This plugin needs no HTML content help */

    parent::__construct();
  }

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;

    $item = GetParm("uploadtree_pk",PARM_INTEGER);
    /* get uploadtree_tablename from $Item */
    $uploadtreeRec = GetSingleRec("uploadtree", "where uploadtree_pk='$item'");
    $uploadRec = GetSingleRec("upload", "where upload_pk='$uploadtreeRec[upload_fk]'");
    if (empty($uploadRec['uploadtree_tablename']))
      $uploadtree_tablename = "uploadtree";
    else
      $uploadtree_tablename = $uploadRec['uploadtree_tablename'];

    $List = GetAllTags($item, true, $uploadtree_tablename);
    foreach($List as $L)
    {
      $V .= $L['tag_name'] . ",";
    }

    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
  }

}
$NewPlugin = new ajax_tags;
$NewPlugin->Initialize();
