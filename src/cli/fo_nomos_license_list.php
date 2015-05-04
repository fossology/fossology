<?php
/***********************************************************
 Copyright (C) 2013-2014 Hewlett-Packard Development Company, L.P.

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

require_once("$MODDIR/lib/php/common-cli.php");
cli_Init();

$Usage = "Usage: " . basename($argv[0]) . "
  -u upload id        :: upload id
  -t uploadtree id    :: uploadtree id
  -c sysconfdir       :: Specify the directory for the system configuration
  --username username :: username
  --password password :: password
  --container         :: include container or not, 1: yes, 0: no (default)
  -x                  :: do not show files which have unuseful license 'No_license_found' or no license
  -X excluding        :: Exclude files containing [free text] in the path.
                         'mac/' should exclude all files in the mac directory.
                         'mac' and it should exclude all files in any directory containing the substring 'mac'
                         '/mac' and it should exclude all files in any directory that starts with 'mac'
  -h  help, this message
  ";
$upload = ""; // upload id
$item = ""; // uploadtree id
$showContainer = 0; // include container or not, 1: yes, 0: no (default)
$ignore = 0; // do not show files which have no license, 1: yes, 0: no (default)
$excluding = '';

$longopts = array("username:", "password:", "container:");
$options = getopt("c:u:t:hxX:", $longopts);
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
    case 'username':
      $user = $value;
      break;
    case 'password':
      $passwd = $value;
      break;
    case 'container':
      $showContainer = $value;
      break;
    case 'x':
      $ignore = 1;
      break;
    case 'X':
      $excluding = $value;
      break;
    default:
      print "unknown option $option\n";
      print $Usage;
  }
}

/** get upload id through uploadtree id */
if (is_numeric($item) && !is_numeric($upload)) $upload = GetUploadID($item);

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

global $PG_CONN;

/** get license information for this uploadtree */
GetLicenseList($item, $upload, $showContainer);
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
  global $ignore;
  global $excluding;
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
    echo _("No data available \n");
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
  $excluding_flag = 0; // 1: exclude 0: not exclude
  while ($row = pg_fetch_assoc($outerresult))
  {
    $filepatharray = array();
    $filepatharray = Dir2Path($row['uploadtree_pk'], $uploadtree_tablename);
    $filepath = "";
    foreach($filepatharray as $uploadtreeRow)
    {
      if (!empty($filepath)) {  // filepath is not empty
        $filepath .= "/";
        /* filepath contains 'xxxx/', '/xxxx/', 'xxxx', '/xxxx' */
          $excluding_flag = ContainExcludeString($filepath, $excluding);
          if (1 == $excluding_flag) {
            break;
          }
        }
      $filepath .= $uploadtreeRow['ufile_name'];
    }
    if (1 == $excluding_flag) continue; // excluding files whose path contains excluding text
    $license_name = GetFileLicenses_string($agent_pk, 0, $row['uploadtree_pk'], $uploadtree_tablename);
    if ($ignore && (empty($license_name) || 'No_license_found' == $license_name)) continue;
    $V = $filepath . ": ". $license_name;
    #$V = $filepath;
    print "$V";
    print "\n";
  }
  pg_free_result($outerresult);
}
