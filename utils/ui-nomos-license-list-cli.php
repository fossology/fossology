#!/usr/bin/php
<?php
/***********************************************************
Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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
Restrict usage: Every PHP file should have this
at the very beginning.
This prevents hacking attempts.
*************************************************/
print "BEGIN\n";
$GlobalReady = 1;
require_once('/usr/share/fossology/php/pathinclude.php');
global $WEBDIR;
require_once("$WEBDIR/common/common.php");

$Usage = "Usage: " . basename($argv[0]) . " [upload] [item] ";

$upload = $argv[1];
$item = $argv[2];
print "Upload:$upload;Item:$item\n";
GetLicenseList($item, $upload);
print "END\n";

function GetLicenseList($uploadtree_pk, $upload_pk) 
{
  global $PG_CONN;
  $PGCONN = dbConnect(NULL);
  if (empty($uploadtree_pk)) return;

  /* get last nomos agent_pk that has data for this upload */
  $Agent_name = "nomos";
  $AgentRec = AgentARSList("nomos_ars", $upload_pk, 1);
  $agent_pk = $AgentRec[0]["agent_fk"];
  if ($AgentRec === false)
  {
    echo _("No data available");
    return;
  }

  /* get the top of tree */
  $sql = "SELECT upload_fk, lft, rgt from uploadtree where uploadtree_pk='$uploadtree_pk'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $toprow = pg_fetch_assoc($result);
  pg_free_result($result); 
  
  /* loop through all the records in this tree */
  $sql = "select uploadtree_pk, ufile_name, lft, rgt from uploadtree 
              where upload_fk='$toprow[upload_fk]' 
                    and lft>'$toprow[lft]'  and rgt<'$toprow[rgt]'
                    and ((ufile_mode & (1<<28)) = 0)";
  $outerresult = pg_query($PG_CONN, $sql);
  DBCheckResult($outerresult, $sql, __FILE__, __LINE__);

  /* Select each uploadtree row in this tree, write out text:
   * filepath : license list
   * e.g. Pound-2.4.tgz/Pound-2.4/svc.c: GPL_v3+, Indemnity
   */
  while ($row = pg_fetch_assoc($outerresult))
  { 
    $filepatharray = Dir2Path($row['uploadtree_pk']);
    $filepath = "";
    foreach($filepatharray as $uploadtreeRow)
    {
      if (!empty($filepath)) $filepath .= "/";
      $filepath .= $uploadtreeRow['ufile_name'];
    }
    $V = $filepath . ": ". GetFileLicenses_string($agent_pk, 0, $row['uploadtree_pk']) ;
    #$V = $filepath;
    print "$V";
    print "\n";
  } 
    pg_free_result($outerresult);
}


?>
