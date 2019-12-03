<?php
/***********************************************************
 Copyright (C) 2012-2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens AG

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
