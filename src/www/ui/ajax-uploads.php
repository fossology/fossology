<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * \file ajax-uploads.php
 * \brief This plugin is used to list all uploads associated
 * with a folder.  This is NOT intended to be a user-UI
 * plugin.
 * This is intended as an active plugin to provide support
 * data to the UI.
 * User must have PERM_WRITE to the uploads.
 */

define("TITLE_CORE_UPLOADS", _("List Uploads as Options"));

class core_uploads extends FO_Plugin
{
  function __construct()
  {
    $this->Name       = "upload_options";
    $this->Title      = TITLE_CORE_UPLOADS;
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
    $uploadList = FolderListUploads_perm($FolderId, Auth::PERM_WRITE);
    foreach ($uploadList as $upload) {
      $V .= "<option value='" . $upload['upload_pk'] . "'>";
      $V .= htmlentities($upload['name']);
      if (! empty($upload['upload_desc'])) {
        $V .= " (" . htmlentities($upload['upload_desc']) . ")";
      }
      if (! empty($upload['upload_ts'])) {
        $V .= " :: " . htmlentities(Convert2BrowserTime($upload['upload_ts']));
      }
      $V .= "</option>\n";
    }
    return new Response($V, Response::HTTP_OK, array('Content-type'=>'text/plain'));
  }
}

$NewPlugin = new core_uploads();
$NewPlugin->Initialize();
