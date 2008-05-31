#!/usr/bin/php
<?php
/*
 cp2foss.php
 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.

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
 * cp2foss: load 1 or more archives into the db, creating folders as needed.
 *
 * @param string $folder_path folder path in the form of my/folder/goes/here
 * @param string $archive_name folder file name to identify the loaded archive
 *        This file folder will be a last entry of the folder path.
 * @param string $archive fully qualified path to archive to load.
 * @param string $description description for the folder, not optional
 * @param string $in_file optional fully qualified path to input file
 *        of archives to load. When this option is used, all other options
 *        are ignored and input comes from the specified file.
 *
 *        Format for the files is the same as command line input.
 *        Blank lines and comments are allowed in the input file.  Comments
 *        are lines that start with a sharp/pound sign. Comments
 *        are ignored.
 * @param string $recurse optional option specified by -r.  Recurse a
 *        directory tree loading all files and sub-directories under the tree.
 *        The -a <archive> option is used to specify the path to the directory
 *        tree to be consumed.
 * @param string $dash-A switch to indicate that alpha folders should be used.
 *        Alpha folders are folders that look like a-c and are used to group
 *        large uploads by the first character of the file folder name.  Using
 *        alpha folders can help keep the left navagation tree smaller.
 *
 * @package cp2foss
 * @author mark.donohoe@hp.com
 * @version $Id$
 *
 *
 * Defect: No way to specify a parent folder description from the cli....
 *
 */

/*
 For versions of items in the db....
 - Will need mutiple schemes.
 - FM: use db and store revision
 - Source repositories: e.g. Fedora, use cvs/svn version?
 - Tree of dirs/files: use db-attributes (put the date/time in there)
 */

// Have to set this or else plugins will not load.
$GlobalReady = 1;

//error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

require_once("pathinclude.h.php");
global $WEBDIR;
global $LIBDIR;

require_once("$WEBDIR/common/common.php");
require_once("$WEBDIR/common/common-cli.php");
require_once("$WEBDIR/template/template-plugin.php");
require_once("$WEBDIR/plugins/core-db.php");
require_once("$LIBDIR/libcp2foss.h.php");


global $Plugins;

$usage =
"Usage: cp2foss [-h] -p <folder-path> -n <upload-name> -a <path-to-archive> -d \"description\" [-f <file-of-parameters>] [-A] [-w] [-R]";

/*
<<< USAGE


   Where:
   <folder-path> is the folder path to store the upload under.
   <upload-name> is the name of the file folder to store this archive in
   <path-to-archive> is the fully qualified path to the compressedd archive.
   <description> is the single or double quoted description.
   <file-of-parameters> is a file that contains the parameters needed to load
   an archive.  Typical usage is to load multiple archives.  For example,
   a file with the contents:
   -p Mysrcs -n foo -a /tmp/mysrcs/foo.c -d "the foo program"
   -p Mysrcs -n bar -a /tmp/mysrcs/bar.c -d "the bar program"
   -p Othersrc -n randy /tmp/other/randy -d "somebodies randy program"

   would load 3 archives, foo.c, bar.c and randy.  Folders Mysrcs and
   Othersrc would be created if they did not exist.  folders for foo
   bar and randy would be placed under the parent folders.
   Mysrcs/foo, Mysrcs/bar, Othersrc/randy.

   -A  turns on alpha bucket mode.  The archive is place in a folder
       corresponding to the first letter of the leaf folder (usually
       the same name as the archive).  For example archive folder ark
       would be placed in the alpha bucket folder a-c. The bucket size is
       3.  For example, d-f. The 'remainder' letters form the last alpha
       bucket.  This option is useful when downloading large number of archives
       like from freshmeat.

    -w Indicates that the argument to -a is going to be a url.  cp2foss
       will use the url supplied and the wget to get the archive before
       uploading.

    -R Turn on recursion.  If the archive is a directory, and this option
       is used, the complete contents of the directory and all subdirectories
       will be tar'ed up and submitted as a single job. If this option is
       omitted and the archive is a directory, only the files in the directory
       will be submitted as a job.  The directory name is the folder name.

USAGE;
*/

cli_Init();
/* Always perform this check after initalizing the environment as
 * the init of the ui is supposed to open the db.
 */

if (empty($DB))
  {
  print "ERROR: Unable to connect to the database.\n";
  exit(1);
  }

global $DB;

// NOTE: replace below with getops for cleaner/more flexible processing....
// Well, prototyped with getopts.  Not much better than the switch below.

/* This check is not sufficient.... has to be 2 to cover the -f <foo> case... */

if ($argc < 2) {
  echo $usage;
  exit(1);
}
$fflag   = 0;
$cap_a   = false;
$dashD   = false;
$recurse = false;

for ($i = 1; $i < $argc; $i++) {
  switch ($argv[$i]) {
    case '-A':
      //$i++;
      $cap_a = true;
      $bucket_size = 3;
      break;
    case '-a':
      $i++;
      //pdbg("MAIN: processing -a");
      if (isset($argv[$i])) {
        $archive = $argv[$i];
      }
      else {
        die("ERROR: Must supply a fully qualified path to an archive after -a\n");
      }
      break;
    case '-d':
      $i++;
      if (isset($argv[$i])) {
        $description = $argv[$i];
        $dashD = True;
      }
      else {
        die("ERROR: Must supply a quoted description after -d\n");
      }
      break;
    case '-f':
      $i++;
      $fflag++;
      if (isset($argv[$i])) {
        $in_file = $argv[$i];
      }
      else {
        die("ERROR: Must supply a valid path to a file after -f\n");
      }
      break;
    case '-n':
      $i++;
      if (isset($argv[$i])) {
        $folder = $argv[$i];
      }
      else {
        die("ERROR: Must specify a folder name after -n\n");
      }
      break;
    case '-p':
      $i++;
      if (isset($argv[$i])) {
        $fpath = $argv[$i];
      }
      else {
        die("ERROR: Must specify a folder path after -p\n");
      }
      break;
    case '-R':
      echo "DBG->setting recurse\n";
      $recurse = TRUE;
      break;
    case '-w':
      $fetch_url = true;
      break;
    case '-h':
      echo $usage;
      exit(0);
      break;
    default:
      die("ERROR: Unknown argument: $argv[$i]\n$usage");
      break;
  }
}
/***********************************************************************
 * Process File input and exit
 * *********************************************************************
 * If we have a file of input, we process it and exit
 * should enhance this to have read_parms_file return a value and
 * exit accordingly.
 *
 * Check this first, so that the other checks are ignored if we have -f
 */
if($fflag){
  if (!(file_exists($in_file))){
    echo "Error, the file $in_file does not exist\n";
    exit(1);
  }
  elseif (($fsize = filesize($in_file)) <= 0){
    echo "Error, file size of $in_file is not greater than zero\n";
    exit(1);
  }
  read_parms_file($in_file);
  exit(1);
}
if (!(isset($fpath))){
  echo "ERROR, -p <parent-folder> is a required parameter\n$usage";
  exit(1);
}

if (!(isset($folder))){
  echo "ERROR, -n <folder> is a required parameter\n$usage";
  exit(1);
}

if (!(isset($archive))){
  echo "ERROR, -a <path-to-archive> is a required parameter\n$usage";
  exit(1);
}

if ($dashD != True){
  echo "ERROR, -d <description> is a required parameter\n$usage";
  exit(1);
}
/***********************************************************************
 * Process file/directory (-R)
 **********************************************************************/
/*
 * is the archive a dir? If so, we suck up the files in it unless
 * recurse is turned on.  Function suckupfs returns false or the tar'ed
 * archive it created.
 */
if(is_dir($archive)){
  if ($recurse){
      cli_PrintDebugMessage("Calling suckupfs with Recurse");
    $archive = suckupfs($archive, $recurse);
  }
  else {
      cli_PrintDebugMessage("Calling suckupfs NO Recurse");
    $archive = suckupfs($archive);
  }
}
cli_PrintDebugMessage("Returned archive from SUPFS is:$archive");
// make sure we didn't get a false from suckupfs.
if (!$archive){
  echo
   "ERROR: there is something wrong with the archive\n\$archive is:$archive\n";
  exit(1);
}
/***********************************************************************
 * wget from url
 **********************************************************************/

// It's either a url or an archive
if($fetch_url){
  // Check to make sure it's a valid http/s request
  if (!(preg_match('|^http[s]*?://|',$archive))){
    echo "ERROR: archive is expected to be a url\n";
    exit(1);
  }
  echo "Getting $archive with wget\n";
  if(!($url_archive = wget_url($archive))){
    echo "Error: wget failed, see previous messages and wget log\n";
    echo "Due to this Fatal Error, no upload can be performed\n";
    exit(1);
  }
  echo "wget done\n";
}
/***********************************************************************
 * Process the archive
 **********************************************************************/
// if archive doesn't exist, stop.
elseif(!(ck_archive($archive))){
  echo "Stopping, can't process archive\n";
  exit(1);
}
// check for slashes and append $alpha folder to the folder path
// strip leading / if there is one, causes problems with explode
$foldr_path = ltrim($fpath, '/');

// strip last charcter if it's a /
$len = strlen($foldr_path);
$len--;
if(($lc = substr($foldr_path,$len,1)) == '/'){
  $foldr_path = rtrim($foldr_path, '/');
}
if($cap_a){
  // Determine the alpha bucket to use
  $alpha_folder = hash2bucket($folder);
  $foldr_path .= "/$alpha_folder";
}
echo
"Working on uploading archive:\n$archive\ninto folder path $foldr_path\n";
echo "Using folder file name $folder to store the archive in\n";

$folder_path = explode('/', $foldr_path);
echo "Checking folder path $foldr_path for existence\n";
// determine what folders exist

$folder_cache = get_fpath_keys($folder_path);

// create any that don't exist and add the folder_pk for that folder.

$folder_cache = create_folders($folder_cache, $folder_path);

if (ck_4_upload($folder_cache, $folder)){
  echo
    "Warning: $folder has already been uploaded into the folder path\n$foldr_path, Skipping...\n\n";
  exit(1);
}

// Get the folder_fk of the last entry in the folder_cache, this should
// be the last folder (leaf). end returns the last value in an associative array.
$folder_fk = end($folder_cache);

if($fetch_url){
  if (!(upload_archive($folder_fk, $folder, $description, $url_archive))){
    echo "Unrecoverable error, during upload\n";
    exit(1);
  }
  else {
    echo "The jobs for the archive\n$archive\nfound in $url_archive have been scheduled\n\n";
  }
}
else{
  if (!(upload_archive($folder_fk, $folder, $description, $archive))){
    echo "Unrecoverable error, during upload\n";
    exit(1);
  }
  else {
    echo "The jobs for the archive\n$archive\nfound in $archive have been scheduled\n\n";
  }
}

// use the variable $foldr_path, as that has the alpha folder on the end of it.
echo
  "Archive:\n$archive\nis scheduled to be loaded into folder:$foldr_path/$folder\n";

exit(0);
// end of MAIN

/**
 * function: ck_archive
 *
 * Determine if the archive exists and that it has size > 0
 *
 * @param string $archive fully qualified path to archvie to be checked.
 *
 * @author mark.donohoe@hp.com
 */

function ck_archive($archive){
  if (false == file_exists($archive)){
    echo "Error, the archive $archive does not exist\n";
    return(false);
  }
  elseif(($fsize = filesize($archive) == 0)){
    echo "Error, file size of $archive is not greater than zero\n";
    return(false);
  }
  return(true);
}
/**
 * function: ck_for_upload
 *
 * Determine if the folder has an upload_pk associated with it.
 *
 * @param array $folder_cache associative array with folder as key and
 *              either the folder_pk or false as the value.
 *
 * @returns true if there is an upload rec and false if there is not.
 *
 * @author mark.donohoe@hp.com
 */
function ck_4_upload($folder_cache, $folder_name){

  // Check to see if the folder has an upload_pk.  If it doesn't
  // then there has never been an upload in the folder.
  // If there is an upload record, we emit a warning and stop processing
  // that archive

  global $DB;

  $folder_pk = end($folder_cache);

  $sql_up =
  "SELECT name, upload_pk FROM leftnav WHERE parent=$folder_pk AND foldercontents_mode=2";

  /* results will be a multi-demension array.  The inner array is assocative with the
   * selected fields as the keys (.e.g. name, upload_pk)
   */

  $results  = $DB->Action($sql_up);
  //cli_PrintDebugMessage("CK4UP: results array after select:",$results);

  $rows = count($results);
  //cli_PrintDebugMessage("CK4UP: \$rows in result is:",$rows);

  // check rows, 0 = no upload rec
  if ($rows == 0){
    return(false);
  }
  elseif ($rows >= 1){
       //cli_PrintDebugMessage("CK4U: \$folder_name is:$folder_name");
    // check to see if this name has been uploaded before
    // If we find it we return true, else false.
    for ($i=0; $i< $rows; $i++){
      $upload_name = $results[$i]['name'];
      if ($upload_name == $folder_name){
        //cli_PrintDebugMessage("CK4UP: \$upload_name matched $folder_name:",$upload_name);
        // if there is an upload_pk, we have already loaded this....
        $upk = $results[$i]['upload_pk'];
        //cli_PrintDebugMessage("CK4UP: \$upload_pk is:",$upk);
        if ($upk == true){
          return(TRUE);
        }
      }
    }
  return(false);
  }
}
/**
 * function:create_folders
 *
 * create folders as needed, given a list of folders and their state.
 *
 * @param array $folder_cache associative array with either the folder_pk or
 *              false for the value of the folder.  Can create with
 *              get_fpath_keys.
 * @param array $folder_path array with a list of the folders, from the
 *              on down.  The first folder in the array is the one associated
 *              with the root folder.
 *
 * @return associative array with folder as key and folder_pk as value.
 *
 * @author mark.donohoe@hp.com
 *
 */
function create_folders($folder_cache, $folder_path){

  // used to determine if we are at the 1st folder.
  $cache_key = key($folder_cache);
  for ($fpi=0; $fpi<count($folder_cache); $fpi++){
    // top folder is a special case
    if ($folder_path[$fpi] == $cache_key){
      if ($folder_cache[$folder_path[$fpi]] === false){
        $fstat = create_parent($folder_path[$fpi]);
        if(! $fstat[$folder_path[$fpi]]){
          echo "Could not create folder $folder_path[$fpi]\n";
          echo "ERROR: Unrecoverable error stopping\n";
          exit(1);
        }
        // update the cache
        else{
          $folder_cache[$folder_path[0]] = $fstat[$folder_path[0]];
          //cli_PrintDebugMessage ("CreFldr: Cache updated after parent create:",$folder_cache);
          continue;
        }
      }
      continue;
    }
    if ($folder_cache[$folder_path[$fpi]] === false){
      //cli_PrintDebugMessage ("CreFldr:values to createFolder:\n {$folder_cache[$folder_path[$fpi-1]]}, $folder_path[$fpi]");
      $fstat = CreateFolder(
      $folder_cache[$folder_path[$fpi-1]], $folder_path[$fpi], '');
      if(! $fstat[$folder_path[$fpi]]){
        echo "Could not create folder $folder_path[$fpi]\n";
        echo "ERROR: Unrecoverable error stopping\n";
        exit(1);
      }
      // update the cache
      else{
        $folder_cache[$folder_path[$fpi]] = $fstat[$folder_path[$fpi]];
      }
    }
  }
  return($folder_cache);
}
/**
 * function:create_parent
 *
 * create a 'parent' folder in the db.  Assumes that the folder does NOT exist.
 * The caller should first verify that the folder doesn't exist.
 * Use folder_exists to determine folder existence.
 *
 * @param string $folder name of the parent folder
 *
 * @return associative array with folder as key and folder_pk as value.
 *
 * @todo think about combining with create_folder
 *
 * @author mark.donohoe@hp.com
 *
 */

function create_parent($folder){
  // all parent folders are created as a child of the root folder for
  // the user (1st release only has one user....)

  global $DB;

  echo "Creating Parent Folder:$folder\n";
  $desc = "Parent folder $folder";
  $sql = 'SELECT root_folder_fk FROM users limit 1';
  $results = $DB->Action($sql);
  $rfolder4user = $results[0]['root_folder_fk'];
  $folder_pk = CreateFolder($rfolder4user, $folder, $desc);
  $name_and_key[$folder] = $folder_pk[$folder];
  return($name_and_key);
}

/**
 * function: get_fpath_keys
 *
 * Get the folder path keys.
 *
 * Given an array of folder names, determine which ones exist.
 *
 * @param string $folder_path the path to the folder.
 *
 * @return associative array with folder name as the key and folder_pk as
 *          the value.  If there is no folder_pk, then the folder doesn't
 *          exist and the value is false.
 *
 * @author mark.donohoe@hp.com
 *
 */

function get_fpath_keys($folder_path){

  global $DB;

  if (empty($folder_path)){
    return(false);
  }
  $folder_cache = array();
  // process the 1st entry specially, as it must be associated with the
  // root.
  $sql = 'select root_folder_fk from users limit 1';
  $results = $DB->Action($sql);
  $rfolder4user = $results[0]['root_folder_fk'];

  $sql_folderP = "Select folder_pk from leftnav where
                  parent='$rfolder4user' and foldercontents_mode=1
                  and name='$folder_path[0]'";

  // If the query below returns nothing, then the folder doesn't exist.
  $results = $DB->Action($sql_folderP);
  $folder_exists_fk = $results[0]['folder_pk'];

  //cli_PrintDebugMessage("GFPK: after parent check, results is:",$results);
  //cli_PrintDebugMessage("GFPK: folder fk is:$folder_pk");

  if ($folder_exists_fk){
    $folder_cache[$folder_path[0]] = $folder_exists_fk;
  }
  else {
    // if the top most folder doesn't exist, then the rest of the
    // subfolders underneath it couldn't exist either.  so fill the
    // cache with 'false' and return.
    foreach ($folder_path as $folder){
      $folder_cache[$folder] = false;
    }
    //cli_PrintDebugMessage("ckFpKeys: after false fill, folder_cache on exit is:",$folder_cache);
    return($folder_cache);
  }
  // now process the rest of the path... Note the array index ($ai)
  // starts at the second array entry.
  $folder_path_size = count($folder_path);
  /*
  * problem may be here, what happens when subfolder doesn't exist?'
  */
  for ($ai=1; $ai < $folder_path_size; $ai++){
    $parent_fk = $folder_cache[$folder_path[$ai-1]];
    //cli_PrintDebugMessage("READP: parent_fk is:$parent_fk");
    $sql_folder = "Select folder_pk from leftnav where
                   name='$folder_path[$ai]' and parent=$parent_fk";
    //cli_PrintDebugMessage("READP: sql is:$sql_folder");
    $results = $DB->Action($sql_folder);
    //cli_PrintDebugMessage ("GFPK: after sql: res[0][fpk]is:",$results);
    $folder_exists = $results[0]['folder_pk'];
    if ($folder_exists){
      $folder_cache[$folder_path[$ai]] = $folder_exists;
    }
    else {
      //cli_PrintDebugMessage("setting remaining entries to false");
      for ($start_here = $ai; $start_here < $folder_path_size; $start_here++){
        $folder_cache[$folder_path[$start_here]] = false;
      }
      break;
    }
  }
  //cli_PrintDebugMessage("ckFpKeys: folder_cache on exit is:",$folder_cache);
  return($folder_cache);
}

/**
 * function: read_parms_file
 *
 * Read a file of input parameters and act on them
 *
 * @param string $parms_file fully qualified path to input file
 *
 * @todo add in check for empty description.
 *
 */
function read_parms_file($parms_file){

  echo "Using file $parms_file for input\n";

  // alpha buckets, urls are off by default.
  $cap_a     = false;
  $fetch_url = false;

  $INfile = fopen("$parms_file", 'r') or
  die("Can't open $parms_file, $php_errormsg\n");

  while(false != ($rline = fgets($INfile, 1024))){
    $line = trim($rline);
    // check for blank lines, (null after trim), skip them
    if ($line === ""){
      continue;
    }
    // check for comments
    if(preg_match('/^#/', $line)){
      continue;
    }
    // Parsing the line is very hard with reqx's. I give up.  So
    // We use the shell to do it for us.... performance at this point
    // is not an issue.
    $dummy = exec("/usr/local/bin/p.sh $rline",$parms, $retval);
    //cli_PrintDebugMessage("after shell call, \$parms is",$parms);

    $pcount = count($parms);
    for($p=0; $p<$pcount; $p++){
      //    echo "\$parms[$p]is:$parms[$p]";
      $token = rtrim($parms[$p]);
      $token = ltrim($token);
      //cli_PrintDebugMessage("\$token is:$token \$p is:$p");
      switch($token){
        case '-A':
          //cli_PrintDebugMessage("Matched -A");
          $cap_a = true;
          //	$p++;
          //	$bucket_size = rtrim($parms[$p]);
          break;
        case '-a':
          $p++;
          $archive = ltrim($parms[$p]);
          $archive = rtrim($archive);
          break;
        case '-d':
          $p++;
          $desc = ltrim($parms[$p]);
          break;
        case '-n':
          $p++;
          //      cli_PrintDebugMessage("Matched -n");
          $raw_name = rtrim($parms[$p]);
          $raw_name = rtrim($raw_name, '\'"');
          $folder_name = ltrim($raw_name, '\'"');
          break;
        case '-p':
          $p++;
          $raw_name = rtrim($parms[$p]);
          $raw_name = rtrim($raw_name, '\'"');
          $fpath = ltrim($raw_name, '\'"');
          break;
        case '-w':
          $fetch_url = true;
          break;
        default:
          echo "ERROR, unsupported option $token\n";
          break;
      }
    }
    // verify input parameters
    // if the description is null (empty), put default in: Future enhancement
    //    cli_PrintDebugMessage("RPF: Out of while loop, checking archive");

    // It's either a url or an archive
    // if archive doesn't exist, don't create folders, skip to next line in
    // input file. Same if the wget does not succeed.
    if($fetch_url){
      //cli_PrintDebugMessage("RP: processing url \$archive is:\n$archive");
      if (!(preg_match('|^http[s]*?://|',$archive))){
        echo "ERROR: archive is expected to be a url\n";
        exit(1);
      }
      echo "Getting $archive\nusing wget\n";
      if(!($url_archive = wget_url($archive))){
        echo "Error: wget failed, see previous messages and wget log\n";
        echo "Due to this Fatal Error, no upload can be performed\n";
        $cap_a     = false;
        $fetch_url = false;
        $parms = array();
        continue;
      }
      echo "Archive sucessfully downloaded to $url_archive\n";
    }
    elseif(!(ck_archive($archive))){
      $cap_a     = false;
      $fetch_url = false;
      $parms = array();
      echo "Warning: Errors with archive Skipping\n";
      continue;
    }

    // determine alpha folder and add to folder path
    // check for slashes and apppend $alpha folder to the folder path
    // Need to check 1st and last character, if /, strip off, explode
    // will return null entries if you don't.

    // strip leading / if there is one
    $fldr_path = ltrim($fpath, '/');

    // strip last charcter if it's a /
    $len = strlen($fldr_path);
    $len--;
    if(($lc = substr($fldr_path,$len,1)) == '/'){
      $fldr_path = rtrim($fldr_path, '/');
    }
    // add in alpha folder?
    if($cap_a){
      // Determine the alpha bucket to use
      $alpha_folder = hash2bucket($folder_name);
      $fldr_path .= "/$alpha_folder";
    }
    echo
      "Working on uploading archive:\n$archive\ninto folder path $fldr_path\n";
    echo "Using folder file name $folder_name to store the archive in\n";
    echo "Determining folder path $fldr_path existence\n";

    $folder_path = explode('/', $fldr_path);
    $folder_cache = get_fpath_keys($folder_path);

    //cli_PrintDebugMessage("READP: folder cache before create_folders",$folder_cache);

    // Create folders in folder path as needed and schedule the upload.
    $folder_cache = create_folders($folder_cache, $folder_path);

    if (ck_4_upload($folder_cache, $folder_name)){
      echo
      "Warning: $folder_name has already been uploaded into the folder path\n$fldr_path, Skipping...\n\n";
      $cap_a     = false;
      $fetch_url = false;
      $parms = array();
      continue;
    }

    // get the folder fk to use in the upload
    $folder_fk = end($folder_cache);

    if($fetch_url){
      if (!(upload_archive($folder_fk, $folder_name, $desc, $url_archive))){
        echo "Unrecoverable error, Stopping Upload for $folder_name\n";
        $cap_a     = false;
        $fetch_url = false;
        $parms = array();
        continue;
      }
      echo "The jobs for the archive\n$archive\nfound in $url_archive have been scheduled\n\n";
    }
    else{
      if (!(upload_archive($folder_fk, $folder_name, $desc, $archive))){
        echo "Unrecoverable error, Stopping Upload for $folder_name\n";
        $cap_a     = false;
        $fetch_url = false;
        $parms = array();
        continue;
      }
      echo "The jobs for the archive\n$archive\n have been scheduled\n\n";
    }
    echo "Archive:\n$archive\nis scheduled to be loaded into folder: $fldr_path/$folder_name\n\n";
    // reset for the next line read
    $cap_a     = false;
    $fetch_url = false;
    $parms = array();
  }
}
/**
 * funciton upload_archive
 *
 * @param int    $folder_fk   the folder foreign key
 * @param string $folder_name the name of the folder
 * @param string $description like it says
 * @param string $archive     the fully qualified path to the archive
 *
 * @return True on success, false otherwise
 *
 */
function upload_archive($folder_fk, $folder_name, $description, $archive){

  // checking for a good archive occurs before this is called.

  global $WEBDIR;
  global $AGENTDIR;

  // create upload rec
  $upload_fk =
  JobAddUpload($folder_name, $archive, $description, 1<<4, $folder_fk);

  // upload the file(s) with wget_agent
  $cmd = "$AGENTDIR/wget_agent -k $upload_fk $archive";
  $lastline = exec($cmd, $output, $retval);
  if($retval != 0) {
    echo "ERROR: could not run wget_agent, return code is:$retval\n";
    return(false);
  }
  /*
   * normally, one would need to schedule an unpack job, but by calling
   * fossjobs, you don't need to.
   *
   * NOTE: we always schedule with a -1 priority so that normal users
   * can use the system.
   */

  $cmd = "fossjobs -U $upload_fk -P -1";
  $last = exec($cmd, $output, $return);
  if ($return != 0)
  {
    echo "Error, could not scheduled agents via fossjobs, error was\n";
    echo $output;
    return(False);
  }
  return(true);
}
?>
