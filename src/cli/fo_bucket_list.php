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
 * @file fo_bucket_list.php
 *
 * @brief get a list of filepaths and bucket information for those
 * files. 
 *
 */

$Usage = "Usage: " . basename($argv[0]) . "
  -u upload id        :: upload id
  -t uploadtree id    :: uploadtree id
  -c sysconfdir       :: optional - Specify the directory for the system configuration
  --user username     :: user name
  --password password :: password
  -b bucket id        :: bucket id
  -a bucket agent id  :: bucket agent id
  -n nomos agent id   :: nomos agent id
  -h  help, this message
  ";

$upload = $item = $bucket = $bucket_agent = $nomos_agent = "";

$longopts = array("user:", "password:");
$options = getopt("c:u:t:b:a:n:h", $longopts);
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
    case 'a':
      $bucket_agent = $value;
      break;
    case 'n':
      $nomos_agent = $value;
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

if (!(is_numeric($item)) &&  !(is_numeric($upload))) {
  print "At least provide uploadtree_id or upload_id.\n";
  print $Usage;
  return 1;
}

/** check upload Id and uploadtree ID */
$upload_from_item = $uploadtree1stid = "";
if (is_numeric($item)) $upload_from_item = GetUploadID($item);
else if (empty($item) && is_numeric($upload)) {
  $uploadtree1stid = Get1stUploadtreeID($upload);
  if (empty($uploadtree1stid)) {
    print "Upload $upload does not exist.\n";
    print $Usage;
    return 1;
  }
  else {
    $item = $uploadtree1stid;
    $upload_from_item = $upload;
  }
}

// print "\$upload_from_item, \$item, \$upload, \$uploadtree1stid are: $upload_from_item, $item, $upload, $uploadtree1stid \n";

if (empty($upload_from_item)) {
  print "Uploadtree ID $item does not exist.\n";
  print $Usage;
  return 1;
} else if (empty($upload)) {
  $upload = $upload_from_item;
} else if ($upload_from_item != $upload) {
  print "Uploadtree ID $item does not under Upload $upload.\n";
  print $Usage;
  return 1;
}

/** check if parameters are valid */
if (!is_numeric($bucket) || !is_numeric($bucket_agent) || !is_numeric($nomos_agent))
{
  print "please enter the correct bucket agent ID and nomos agent ID and bucket ID.\n";
  // print "\$upload, \$item are $upload, $item \n";
  Usage4Options($upload, $item);
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
GetBucketList($bucket, $bucket_agent, $nomos_agent, $item, $upload);
return 0;

/**
 * \brief get bucket list of one specified upload or all uploads
 *
 * \pamam $upload_pk - upload id
 * \param $bucket_pk - bucket id
 * \param $bucket_agent - bucket agent ID
 * \param $nomos_agent - nomos agent ID
 * \prram $uploadtree_pk - uploadtree ID
 */
function GetBucketList($bucket_pk, $bucket_agent, $nomos_agent, $uploadtree_pk, $upload_pk = 0)
{
  global $PG_CONN;

  /** get bucket name */
  $sql = "SELECT bucket_name from bucket_def where bucket_pk = $bucket_pk;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);

  $uploadtree_tablename = GetUploadtreeTableName($upload_pk);

  /* get the top of tree */
  $sql = "SELECT upload_fk, lft, rgt, uploadtree_pk  from $uploadtree_tablename";
  
  if ($uploadtree_pk){ // if uploadtree_pk is null, that means get all data on an upload 
    $sql .= " where uploadtree_pk='$uploadtree_pk';";
  }
  else {
    $sql .= " where upload_fk='$upload_pk' and parent is null;";
  }
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $toprow = pg_fetch_assoc($result);
  $uploadtree_pk = $toprow['uploadtree_pk'];
  pg_free_result($result);

  if (empty($toprow)) {
    print "Sorry, Can not find upload $upload_pk.\n";
    return 1;
  }

  print "For uploadtree $uploadtree_pk under upload $upload_pk has bucket $row[bucket_name]:\n";
  /* loop through all the records in this tree */
  $sql = "select uploadtree_pk, ufile_name, lft, rgt from $uploadtree_tablename, bucket_file
    where upload_fk=$upload_pk
    and lft>'$toprow[lft]'  and rgt<'$toprow[rgt]'
    and ((ufile_mode & (1<<28)) = 0)  and ((ufile_mode & (1<<29)) = 0) and bucket_file.pfile_fk = $uploadtree_tablename.pfile_fk
    and bucket_fk = '$bucket_pk' and agent_fk = '$bucket_agent' and nomosagent_fk = '$nomos_agent'
    order by uploadtree_pk";
  $outerresult = pg_query($PG_CONN, $sql);

  /* Select each uploadtree row in this tree, write out text */
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

function Usage4Options($UploadID, $item)
{
  global $PG_CONN;
  $sql = "SELECT agent_fk as bucket_agent_id, nomosagent_fk as nomos_agent_id, bucket_pk as bucket_id, bucket_ars.bucketpool_fk as bucketpoo_id, bucket_name from bucket_ars right join bucket_def on bucket_def.bucketpool_fk = bucket_ars.bucketpool_fk where upload_fk =  '$UploadID';";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $bucket_arr = pg_fetch_all($result);
  pg_free_result($result);
  $clause4uploadtree = "";
  if ($item) $clause4uploadtree = " uploadtree $item";
  if ($bucket_arr) {
    print "For"."$clause4uploadtree under upload $UploadID, you can specify options below: \n 
      bucket_agent_id : -a
      nomos_agent_id  : -n
      bucket_id       : -b
      \n";
    print_r($bucket_arr);
  }
  else {
    print "Please confirm uploadtree $item under upload $UploadID has done one bucket scanning.\n";
  }
}

?>
