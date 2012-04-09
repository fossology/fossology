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

/**
 * @file fo_copyright_list.php
 *
 * @brief This file is a quick hack to get a list of filepaths and copyright information for those
 * files.  As is it isn't ready for prime time but needs to solve an immediate
 * problem.  I'll improve this at a future date but wanted to check it in
 * because others my find it useful.
 *
 * @note default you can use this script on source installation system, if you want too use it on
 * package installation system, please replace the line postfix with '// install from source code'
 * with the line postfix with '// install from package'
 *
 */

print "BEGIN\n";
//$WEBDIR = "/usr/share/fossology/"; // install from package
$WEBDIR = "/usr/local/share/fossology/"; // install from source code
require_once("$WEBDIR/lib/php/common.php");

$Usage = "Usage: " . basename($argv[0]) . " [upload] [item] [type(statement/url/email)]";
$upload = $item = $type = "";

if ($argc ==  3) 
{
  $upload = $argv[1];
  $item = $argv[2];
} 

if ($argc == 4)
{
  $upload = $argv[1];
  $item = $argv[2];
  $type = $argv[3];
}


print "Upload:$upload;Item:$item\n";
$SysConf['DBCONF']['dbname'] = "fossology";
GetCopyrightList($item, $upload, $type);
print "END\n";

function GetCopyrightList($uploadtree_pk, $upload_pk, $type) 
{
  //$PG_CONN =  DBconnect("/etc/fossology/"); // install from package
  $PG_CONN =  DBconnect("/usr/local/etc/fossology/"); // install from source
  if (empty($uploadtree_pk)) return;

  /* get last copyright agent_pk that has data for this upload */
  $Agent_name = "copyright";
  $AgentRec = AgentARSList("copyright_ars", $upload_pk, 1);
  $agent_pk = $AgentRec[0]["agent_fk"];
  if ($AgentRec === false)
  {
    echo _("No data available");
    return;
  }

  /* get the top of tree */
  $sql = "SELECT upload_fk, lft, rgt from uploadtree where uploadtree_pk='$uploadtree_pk';";
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
   * filepath : copyright list
   * e.g. copyright (c) 2011 hewlett-packard development company, l.p.
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
    $V = $filepath . ": ". GetFileCopyright_string($agent_pk, 0, $row['uploadtree_pk'], $type) ;
    #$V = $filepath;
    print "$V";
    print "\n";
  } 
    pg_free_result($outerresult);
}


?>
