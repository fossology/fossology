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
 * \file ajax-filebucket.php
 * \brief  This plugin finds all the uploadtree_pk's in the first directory
 * level under a parent, that contains a given bucket.
 * GET args: \n
 *  item        parent uploadtree_pk \n
 *  bucket_pk   bucket_pk \n
 *
 * ajax usage: \n
 *  http://...?mod=ajax_filebucket&item=23456&bucket_pk=27
 *
 * \return a comma delimited string of bucket_pk followed by uploadtree_pks:
 * "12,999,123,456"
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
   * \brief Display the loaded menu and plugins.
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
