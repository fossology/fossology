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
 * @file fo_nomos_license_list.php
 *
 * @brief This file is a quick hack to get a list of filepaths and nomos license information for those
 * files.  As is it isn't ready for prime time but needs to solve an immediate
 * problem.  we will improve this at a future date but wanted to check it in
 * because others my find it useful.
 *
 * @note default you can use this script on source installation system, if you want too use it on
 * package installation system, please replace the line postfix with '// install from source code'
 * with the line postfix with '// install from package'
 *  
 */

$Usage = "Usage: " . basename($argv[0]) . " -u upload_id - t uploadtree_id -c sysconf_dir -h \n";
$upload = ""; // upload id
$item = ""; // uploadtree id

$options = getopt("c:u:t:h");
if (!is_array($options)) 
{ 
  print $Usage;
  return 1;
}
foreach($options as $option => $value)
{
  switch($option)
  {
    case 'c':
      $sysconfdir = $value;
      break;
    case 'u':
      $upload = $value;
      break;
    case 't':
      $item = $value;
      break;
    case 'h':
      print $Usage;
      return 1;
    default:
      print $Usage;
      return 1;
  }
}

if (!is_numeric($upload) || !is_numeric($item))
{
  print "Upload ID or Uploadtree ID is not digital number\n";
  print $Usage;
  return 1;
}

print "Upload ID:$upload; Uploadtree ID:$item\n";

require_once("$MODDIR/lib/php/common.php");
global $PG_CONN;

GetLicenseList($item, $upload);
print "END\n";

function GetLicenseList($uploadtree_pk, $upload_pk) 
{
  global $PG_CONN;
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
  $sql = "SELECT upload_fk, lft, rgt from uploadtree where uploadtree_pk='$uploadtree_pk';";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $toprow = pg_fetch_assoc($result);
  pg_free_result($result); 
  
  $uploadtree_tablename = GetUploadtreeTableName($toprow['upload_fk']);

  /* loop through all the records in this tree */
  $sql = "select uploadtree_pk, ufile_name, lft, rgt from $uploadtree_tablename 
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
    $filepatharray = Dir2Path($row['uploadtree_pk'], $uploadtree_tablename);
    $filepath = "";
    foreach($filepatharray as $uploadtreeRow)
    {
      if (!empty($filepath)) $filepath .= "/";
      $filepath .= $uploadtreeRow['ufile_name'];
    }
    $V = $filepath . ": ". GetFileLicenses_string($agent_pk, 0, $row['uploadtree_pk'], $uploadtree_tablename) ;
    #$V = $filepath;
    print "$V";
    print "\n";
  } 
    pg_free_result($outerresult);
}


?>
