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

/**
 * \file ajax-uploads.php
 * \brief This plugin is used to list all uploads associated
 * with a folder.  This is NOT intended to be a user-UI
 * plugin. 
 * This is intended as an active plugin to provide support
 * data to the UI.
 * User must have PERM_WRITE to the uploads.
 */

define("TITLE_core_uploads", _("List Uploads as Options"));

class core_uploads extends FO_Plugin
{
  function __construct()
  {
    $this->Name       = "upload_options";
    $this->Title      = TITLE_core_uploads;
    $this->DBaccess   = PLUGIN_DB_READ;
    $this->OutputType = 'Text'; /* This plugin needs no HTML content help */

    parent::__construct();
  }

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    $FolderId = GetParm("folder",PARM_INTEGER);
    if (empty($FolderId)) {
      $FolderId = FolderGetTop();
    }
    $V = '';
    $uploadList = FolderListUploads_perm($FolderId, PERM_WRITE);
    foreach($uploadList as $upload)
    {
      $V .= "<option value='" . $upload['upload_pk'] . "'>";
      $V .= htmlentities($upload['name']);
      if (!empty($upload['upload_desc']))
      {
        $V .= " (" . htmlentities($upload['upload_desc']) . ")";
      }
      if (!empty($upload['upload_ts']))
      {
        $V .= " :: " . htmlentities($upload['upload_ts']);
      }
      $V .= "</option>\n";
    }
    return $V;
  }

}
$NewPlugin = new core_uploads;
$NewPlugin->Initialize();
