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
 * @brief get a list of filepaths and copyright information for those
 * files. 
 *
 */

$Usage = "Usage: " . basename($argv[0]) . "
  -u upload id        :: upload id
  -t uploadtree id    :: uploadtree id
  -c sysconfdir       :: Specify the directory for the system configuration
  --type type         :: all/statement/url/email, default: all
  --user username     :: user name
  --password password :: password
  -h  help, this message
  ";

$upload = $item = $type = "";

$longopts = array("user:", "password:", "type:");
$options = getopt("c:u:t:h", $longopts);
if (empty($options) || !is_array($options))
{
  print $Usage;
  return 1;
}

$user = $passwd = "";
foreach($options as $option => $value)
{
  switch($option)
  {
    case 'c': // handled in fo_wrapper
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
    case 'user':
      $user = $value;
      break;
    case 'password':
      $passwd = $value;
      break;
    case 'type':
      $type = $value;
      break;
    default:
      print $Usage;
      return 1;
  }
}

/** check if parameters are valid */
if (!is_numeric($upload) || !is_numeric($item))
{
  print "Upload ID or Uploadtree ID is not digital number\n";
  print $Usage;
  return 1;
}

print "Upload ID:$upload; Uploadtree ID:$item\n";

account_check($user, $passwd); // check username/password

$return_value = read_permission($upload, $user); // check if the user has the permission to read this upload
if (empty($return_value))
{
  $text = _("The user '$user' has no permission to read the information of upload $upload\n");
  echo $text;
  return 1;
}

require_once("$MODDIR/lib/php/common.php");

/** get copyright information for this uploadtree */
GetCopyrightList($item, $upload, $type);
print "END\n";
return 0;

/**
 * \brief get copyright list of one specified uploadtree_id
 *
 * \pamam $uploadtree_pk - uploadtree id
 * \pamam $upload_pk - upload id
 * \param $type copyright type(all/statement/url/email)
 */
function GetCopyrightList($uploadtree_pk, $upload_pk, $type) 
{
  global $PG_CONN;
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

  $uploadtree_tablename = GetUploadtreeTableName($toprow['upload_fk']);
  
  /* loop through all the records in this tree */
  $sql = "select uploadtree_pk, ufile_name, lft, rgt from $uploadtree_tablename 
              where upload_fk='$toprow[upload_fk]' 
                    and lft>'$toprow[lft]'  and rgt<'$toprow[rgt]'
                    and ((ufile_mode & (1<<28)) = 0) order by uploadtree_pk";
  $outerresult = pg_query($PG_CONN, $sql);
  DBCheckResult($outerresult, $sql, __FILE__, __LINE__);

  /* Select each uploadtree row in this tree, write out text:
   * filepath : copyright list
   * e.g. copyright (c) 2011 hewlett-packard development company, l.p.
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
    $V = $filepath . ": ". GetFileCopyright_string($agent_pk, 0, $row['uploadtree_pk'], $type) ;
    #$V = $filepath;
    print "$V";
    print "\n";
  } 
    pg_free_result($outerresult);
}

?>
