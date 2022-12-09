<?php
/*
 SPDX-FileCopyrightText: © 2012-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Symfony\Component\HttpFoundation\Response;

define("TITLE_UPLOAD_TAGGING", _("Manage Upload Tagging"));

class upload_tagging extends FO_Plugin
{
  var $Name       = "upload_tagging";
  var $Title      = TITLE_UPLOAD_TAGGING;
  var $Version    = "1.0";
  var $Dependency = array();
  var $DBaccess   = PLUGIN_DB_ADMIN;
  var $NoHTML     = 1; /* This plugin needs no HTML content help */

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    $upload_id = GetParm("upload",PARM_INTEGER);
    if (empty($upload_id)) {
      return new Response('', Response::HTTP_OK,array('content-type'=>'text/plain'));
    }

    /** check if this upload has been disabled */
    $sql = "select count(*) from tag_manage where upload_fk = $1 and is_disabled = true";
    $numTags = $GLOBALS['container']->get('db.manager')->getSingleRow($sql,array($upload_id));
    $count = $numTags['count'];
    if (empty($count)) { // enabled
      $text = _("Disable");
      $V = "<input type='submit' name='manage'  value='$text'>\n";
    } else { // disabled
      $text = _("Enable");
      $V = "<input type='submit' name='manage' value='$text'>\n";
    }

    return new Response($V, Response::HTTP_OK,array('content-type'=>'text/plain'));
  }
}

$NewPlugin = new upload_tagging;
$NewPlugin->Initialize();
