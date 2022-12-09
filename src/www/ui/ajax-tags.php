<?php
/*
 SPDX-FileCopyrightText: © 2010-2012 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Symfony\Component\HttpFoundation\Response;

define("TITLE_AJAX_TAGS", _("List Tags"));

class ajax_tags extends FO_Plugin
{
  function __construct()
  {
    $this->Name       = "tag_get";
    $this->Title      = TITLE_AJAX_TAGS;
    $this->DBaccess   = PLUGIN_DB_READ;
    $this->OutputType = 'Text'; /* This plugin needs no HTML content help */

    parent::__construct();
  }

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    $V="";

    $item = GetParm("uploadtree_pk",PARM_INTEGER);
    /* get uploadtree_tablename from $Item */
    $uploadtreeRec = GetSingleRec("uploadtree", "where uploadtree_pk='$item'");
    $uploadRec = GetSingleRec("upload", "where upload_pk='$uploadtreeRec[upload_fk]'");
    if (empty($uploadRec['uploadtree_tablename'])) {
      $uploadtree_tablename = "uploadtree";
    } else {
      $uploadtree_tablename = $uploadRec['uploadtree_tablename'];
    }

    $List = GetAllTags($item, true, $uploadtree_tablename);
    foreach ($List as $L) {
      $V .= $L['tag_name'] . ",";
    }

    return new Response($V, Response::HTTP_OK,array('content-type'=>'text/plain'));
  }
}

$NewPlugin = new ajax_tags();
$NewPlugin->Initialize();
