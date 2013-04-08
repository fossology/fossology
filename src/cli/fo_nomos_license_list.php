<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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
 * @brief get a list of filepaths and nomos license information for those
 * files. 
 */

$Usage = "Usage: " . basename($argv[0]) . "
  -u upload id        :: upload id
  -t uploadtree id    :: uploadtree id
  -c sysconfdir       :: Specify the directory for the system configuration
  --user username     :: user name
  --password password :: password
  --container         :: include container or not, 1: yes, 0: no (default)
  -h  help, this message
  ";
$upload = ""; // upload id
$item = ""; // uploadtree id
$container = 0; // include container or not, 1: yes, 0: no (default)

$longopts = array("user:", "password:", "container:");
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
    case 'container':
      $container = $value;
      break;
    default:
      print "unknown option $option\n";
      print $Usage;
  }
}

/** check if parameters are valid */
if (!is_numeric($upload) || (!empty($item) && !is_numeric($item)))
{
  print "Upload ID or Uploadtree ID is not digital number\n";
  print $Usage;
  return 1;
}

account_check($user, $passwd); // check username/password

$return_value = read_permission($upload, $user); // check if the user has the permission to read this upload
if (empty($return_value))
{
  $text = _("The user '$user' has no permission to read the information of upload $upload\n");
  echo $text;
  return 1;
}

require_once("$MODDIR/lib/php/common.php");
global $PG_CONN;

/** get license information for this uploadtree */
GetLicenseList($item, $upload, $container);
print "END\n";
return 0;

/**
 * \brief get nomos license list of one specified uploadtree_id
 *
 * \param $uploadtree_pk - uploadtree id
 * \param $upload_pk - upload id
 * \param $container - include container or not, 1: yes, 0: no (default)
 */
function GetLicenseList($uploadtree_pk, $upload_pk, $container = 0) 
{
  global $PG_CONN;
  if (empty($uploadtree_pk)) {
      /* Find the uploadtree_pk for this upload so that it can be used in the browse link */
      $uploadtreeRec = GetSingleRec("uploadtree", "where parent is NULL and upload_fk='$upload_pk'");
      $uploadtree_pk = $uploadtreeRec['uploadtree_pk'];
  }

//  print "Upload ID:$upload_pk; Uploadtree ID:$uploadtree_pk\n";

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
  $container_sql = " and ((ufile_mode & (1<<29)) = 0)";
  /* include container or not */
  if (empty($container)) {
    $sql .= $container_sql; // do not include container
  }
  $sql .= "order by uploadtree_pk";
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
