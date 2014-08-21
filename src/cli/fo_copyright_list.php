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
 * @file fo_copyright_list.php
 *
 * @brief get a list of filepaths and copyright information for those
 * files. 
 *
 */

$Usage = "Usage: " . basename($argv[0]) . "
  -u upload id        :: required - upload id
  -t uploadtree id    :: required - uploadtree id
  -c sysconfdir       :: optional - Specify the directory for the system configuration
  --type type         :: optional - all/statement/url/email, default: all
  --user username     :: user name
  --password password :: password
  --container         :: include container or not, 1: yes, 0: no (default)
  -x copyright        :: to match all that does not contain my copyright, (default): show all files
  -X copyright        :: to match my copyright, (default): show all files
  -h  help, this message
  ";

$upload = $item = $type = "";

$longopts = array("user:", "password:", "type:", "container:");
$options = getopt("c:u:t:hx:X:", $longopts);
if (($options === false) || empty($options) || !is_array($options))
{
  $text = _("Invalid option or missing argument.");
  print "$text\n";
  print $Usage;
  return 1;
}

$user = $passwd = "";
$container = 0; // include container or not, 1: yes, 0: no (default)
$including_copyright = -1; // to match mycopyright
$excluding_copyright = -1; // match all that does not contain mycopyright

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
    case 'container':
      $container = $value;
      break;
    case 'x': // exclude my copyright
      $excluding_copyright = $value;
      break;
    case 'X': // include my copyright
      $including_copyright = $value;
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
  print "Upload ID or Uploadtree ID must be numeric\n";
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

/** get copyright information for this uploadtree */
GetCopyrightList($item, $upload, $type, $container);
return 0;

/**
 * \brief get copyright list of one specified uploadtree_id
 *
 * \pamam $uploadtree_pk - uploadtree id
 * \pamam $upload_pk - upload id
 * \param $type copyright type(all/statement/url/email)
 * \param $container - include container or not, 1: yes, 0: no (default)
 */
function GetCopyrightList($uploadtree_pk, $upload_pk, $type, $container = 0) 
{
  global $PG_CONN;
  global $excluding_copyright;
  global $including_copyright;
  if (empty($uploadtree_pk)) {
      /* Find the uploadtree_pk for this upload so that it can be used in the browse link */
      $uploadtreeRec = GetSingleRec("uploadtree", "where parent is NULL and upload_fk='$upload_pk'");
      $uploadtree_pk = $uploadtreeRec['uploadtree_pk'];
  }

//  print "Upload ID:$upload_pk; Uploadtree ID:$uploadtree_pk\n";

  /* get last copyright agent_pk that has data for this upload */
  $Agent_name = "copyright";
  $AgentRec = AgentARSList("copyright_ars", $upload_pk, 1);
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
  $sql .= " order by uploadtree_pk";
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

    $copyright = GetFileCopyright_string($agent_pk, 0, $row['uploadtree_pk'], $type) ;
    /** include and exclude together */
    if (-1 != $including_copyright && -1 != $excluding_copyright && !empty($including_copyright) && 
        !empty($excluding_copyright))
    {
      if (!empty($copyright) && stristr($copyright, $including_copyright) && 
          !stristr($copyright, $excluding_copyright)) ;
      else {
        continue;
      }
    }
    else if (
        /** no value set for -x and -X, show all files */ 
        (-1 == $including_copyright && -1 == $excluding_copyright) ||
        /** both value from -x and -X are empty, unmeaningful, show all files */
        (empty($including_copyright) && empty($excluding_copyright)) ||
        /** just show files without copyright no matter if excluding_copyright */
        (empty($including_copyright) && empty($copyright)) ||
        /** just show files with copyright */
        (empty($excluding_copyright) && !empty($copyright)) ||
        /** include  */
        (-1 != $including_copyright && !empty($including_copyright) && !empty($copyright) && stristr($copyright, $including_copyright))  ||
        /** exclude */
        (-1 != $excluding_copyright && !empty($excluding_copyright) && !empty($copyright) && !stristr($copyright, $excluding_copyright))) ;
    else continue;
    {
      $V = $filepath . ": ". $copyright;
      print "$V";
      print "\n";
    }
  } 
    pg_free_result($outerresult);
}

?>
