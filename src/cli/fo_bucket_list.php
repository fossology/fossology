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
 * @file fo_bucket_list.php
 *
 * @brief get a list of filepaths and bucket information for those
 * files. 
 *
 */

$Usage = "Usage: " . basename($argv[0]) . "
  -u upload id        :: required - upload id
  -c sysconfdir       :: optional - Specify the directory for the system configuration
  --user username     :: user name
  --password password :: password
  -b                  :: required - bucket id
  -h  help, this message
  ";

$upload = $bucket = "";

$longopts = array("user:", "password:");
$options = getopt("c:u:b:h", $longopts);
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
    case 'b':
      $bucket = $value;
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
    default:
      print "unknown option $option\n";
      print $Usage;
  }
}

/** check if parameters are valid */
if (!is_numeric($bucket) && !empty($upload) && !is_numeric($upload))
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

/** get bucket information for this uploadtree */
GetBucketList($bucket, $upload);
return 0;

/**
 * \brief get bucket list of one specified upload or all uploads
 *
 * \pamam $upload_pk - upload id
 * \param $bucket_pk - bucket id
 */
function GetBucketList($bucket_pk, $upload_pk = 0)
{
  global $PG_CONN;

  $sql = "SELECT bucket_name from  bucket_def where bucket_pk = $bucket_pk;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);
  if ($upload_pk) $text = "upload $upload_pk";
  else $text = "all uploads";
  print "Get all files have bucket $row[bucket_name] for $text \n";
  $uploadtree_tablename = GetUploadtreeTableName($upload_pk);
  $sql_upload = '';
  if ($upload_pk) $sql_upload = "and upload_fk = $upload_pk";
  $sql = "SELECT DISTINCT uploadtree_pk from pfile, bucket_file, uploadtree where pfile_pk = bucket_file.pfile_fk and pfile_pk = uploadtree.pfile_fk and bucket_fk = $bucket_pk and ((ufile_mode & (1<<28)) = 0) and ((ufile_mode & (1<<29)) = 0) $sql_upload order by uploadtree_pk;";
  $outerresult = pg_query($PG_CONN, $sql);
  DBCheckResult($outerresult, $sql, __FILE__, __LINE__);

  /* Select each uploadtree row in this tree, write out text:
   * filepath : bucket list
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
    $V = $filepath;
    print "$V";
    print "\n";
  } 
    pg_free_result($outerresult);
}

?>
