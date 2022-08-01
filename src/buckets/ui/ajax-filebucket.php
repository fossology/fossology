<?php
/*
 SPDX-FileCopyrightText: © 2010-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file ajax-filebucket.php
 * This plugin finds all the uploadtree_pk's in the first directory
 * level under a parent, that contains a given bucket.
 *
 * GET args: \n
 *  item        parent uploadtree_pk \n
 *  bucket_pk   bucket_pk \n
 *
 * ajax usage: \n
 *  http://...?mod=ajax_filebucket&item=23456&bucket_pk=27
 */

define("TITLE_ajax_filebucket", _("List Uploads as Options"));

class ajax_filebucket extends FO_Plugin
{
  function __construct()
  {
    $this->Name       = "ajax_filebucket";
    $this->Title      = TITLE_ajax_filebucket;
    $this->DBaccess   = PLUGIN_DB_READ;
    $this->LoginFlag = 0;
    parent::__construct();
  }

  /**
   * @brief Display the loaded menu and plugins.
   * @return string a comma delimited string of bucket_pk followed by uploadtree_pks:
   * "12,999,123,456"
   * @see FO_Plugin::Output()
   */
  function Output()
  {
    global $PG_CONN;

    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    if (!$PG_CONN) {
      return "NO DB connection";
    }

    $bucket_pk = GetParm("bucket_pk",PARM_RAW);
    $uploadtree_pk = GetParm("item",PARM_INTEGER);

    /* Get the uploadtree table name */
    $uploadtree_rec = GetSingleRec("uploadtree", "where uploadtree_pk='$uploadtree_pk'");
    $uploadtree_tablename = GetUploadtreeTableName($uploadtree_rec['upload_fk']);

    /* Get all the non-artifact children */
    $children = GetNonArtifactChildren($uploadtree_pk, $uploadtree_tablename);

    /* Loop through children and create a list of those that contain $bucket_pk */
    $outstr = $bucket_pk;
    foreach ($children as $child)
    {
      if (BucketInTree($bucket_pk, $child['uploadtree_pk']))
      {
        $outstr .= ",$child[uploadtree_pk]";
      }
    }

    return $outstr;
  }

}
$NewPlugin = new ajax_filebucket;
$NewPlugin->Initialize();
