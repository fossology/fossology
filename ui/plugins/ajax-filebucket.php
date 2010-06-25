<?php
/***********************************************************
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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

/*************************************************
 This plugin finds all the uploadtree_pk's in the first directory
 level under a parent, that contains a given bucket.

 GET args: 
   bapk        bucket agent pk 
   item        parent uploadtree_pk
   bucket_pk   bucket_pk

 ajax usage:
   http://...?mod=ajax_filebucket&bapk=1234&item=23456&bucket_pk=27
 
 Returns a comma delimited string of bucket_pk followed by uploadtree_pks: 
  "12,999,123,456"
 *************************************************/

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }


class ajax_filebucket extends FO_Plugin
{
  var $Name       = "ajax_filebucket";
  var $Title      = "List Uploads as Options";
  var $Version    = "1.0";
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_READ;
  var $NoHTML     = 1; /* This plugin needs no HTML content help */
  var $LoginFlag = 0;

  /***********************************************************
   Output(): Display the loaded menu and plugins.
   ***********************************************************/
  function Output()
  {  
    global $DB, $PG_CONN;
    global $Plugins;

    if ($this->State != PLUGIN_STATE_READY) { return; }
    //$uTime = microtime(true);

    // make sure there is a db connection since I've pierced the core-db abstraction
    if (!$PG_CONN) { $dbok = $DB->db_init(); if (!$dbok) echo "NO DB connection"; }

	$agent_pk = GetParm("bapk",PARM_INTEGER);
    $bucket_pk = GetParm("bucket_pk",PARM_RAW);
    $uploadtree_pk = GetParm("item",PARM_INTEGER);

    /* Get all the non-artifact children */
    $children = GetNonArtifactChildren($uploadtree_pk);

    /* Loop through children and create a list of those that contain $bucket_pk */
    $outstr = $bucket_pk;
    foreach ($children as $child)
    {
      $sql = "select uploadtree_pk from uploadtree,bucket_file 
               where uploadtree_pk='$child[uploadtree_pk]'
                     and uploadtree.pfile_fk=bucket_file.pfile_fk
                     and bucket_fk='$bucket_pk' and agent_fk='$agent_pk'
               limit 1";
//echo $sql, "<br>";

      $result = pg_query($PG_CONN, $sql);  // Top uploadtree_pk's
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      if (pg_num_rows($result) > 0) 
      {
        $row = pg_fetch_assoc($result);
        $outstr .= ",$row[uploadtree_pk]";
      }
      pg_free_result($result);
    }

    if (!$this->OutputToStdout) { return($outstr); }
    print("$outstr");
    return;
  } // Output()


};
$NewPlugin = new ajax_filebucket;
$NewPlugin->Initialize();

?>
