#!/usr/bin/php

<?php
/***********************************************************
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
 * @version $Id: cp2foss.php 1553 2007-12-10 18:58:44Z markd $
 *
 * @todo remove default 'parent folder'.
 * @todo Add in recursion
 * @todo finish parameter checks.
 * @todo Finsih doc'ing all functions etc...
 *
 * Defect: No way to specify a parent folder description from the cli....
 *
 * Issue with recursion: the folder parameter doesn't make much sense in this
 * case, but the parent folder does....
 *
 */

/*
 For versions of items in the db....
 - Will need mutiple schemes.
 - FM: use db and store revision
 - Source repositories: e.g. Fedora, use cvs/svn version?
 - Tree of dirs/files, no good solution, use last mod time on each dir/file?
 */

require_once("pathinclude.h.php");
require_once("$WEBDIR/webcommon.h.php");
require_once("$WEBDIR/jobs.h.php");
require_once("$WEBDIR/db_postgres.h.php");
//require_once("$LIBDIR/libcp2foss.h.php");
require_once("./libcp2foss.h.php");

$usage = <<< USAGE
Usage: cp2foss [-h] -p <folder-path> -n <upload-name> -a <path-to-archive> \
       -d "description" [-f <file-of-parameters>] [-A] [-w]
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
         
USAGE;

$parent_folder = 'Repository Directory';  // default if non given

// NOTE: replace below with getops for cleaner/more flexible processing....
// for example, can't really have an optional parent dir (default), nor
// description, or -r (future). As written...
// Well, prototyped with getopts.  Not much better than the switch below.
// Not quite up to snuff in terms compared to perl or glibc...

// This check is not sufficient.... has to be 2 to cover the -f <foo> case...
if ($argc < 2) {
  echo $usage;
  exit(1);
}

$fflag = 0;
$cap_a = false;

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
        die("ERROR: Must supply a fully qualified path to an archive after -a");
      }
      break;
    case '-d':
      $i++;
      if (isset($argv[$i])) {
        $description = $argv[$i];
      }
      else {
        die("ERROR: Must supply a quoted description after -d");
      }
      break;
    case '-f':
      $i++;
      $fflag++;
      if (isset($argv[$i])) {
        $in_file = $argv[$i];
      }
      else {
        die("ERROR: Must supply a valid path to a file after -f");
      }
      break;
    case '-n':
      $i++;
      if (isset($argv[$i])) {
        $folder = $argv[$i];
      }
      else {
        die("ERROR: Must specify a folder name after -n");
      }
      break;
    case '-p':
      $i++;
      if (isset($argv[$i])) {
        $fpath = $argv[$i];
      }
      else {
        die("ERROR: Must specify a folder path after -p");
      }
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


// BUG: need to parameter check, did we get all required parameters?

//echo "PARAMETERS:\n";
//echo "PF:$parent_folder\nN:$folder\nD:$description\nA:$archive\nFURL:$fetch_url\n\n";

$path = "{$DATADIR}/dbconnect/{$PROJECT}";

db_init($path);

if (!$_pg_conn) {
  echo "ERROR: could not connect to DB\n";
  exit(1);
}
/*
 enhancement area:
 This is spot to check for:
 1. default parent folder
 2. no description, create as needed
 3. any special setups needed for recursion?
 - folder-path: e.g Fedora-8 or FreshMeat
 - folder: The name passed in to use for the archive file folder
 - For example, if -p FreshMeat -n buzzard is passed in, the following
 folder structure will be created.

 <top/root folder>.../FreshMeat/a-c/buzzard

 */

// If we have a file of input, we process it and exit
// should enhance this to have read_parms_file return a value and
// exit accordingly.
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
  exit;
}

/*
 * Stub for recursion
 if($recurse_flag){
 // if it's a file, print warning, but process anyway.
 // check if the dir pointed to in archive, exists.
 //
 }
 */

// It's either a url or an archive
if($fetch_url){
  //pdbg("MAIN: processing url \$archive is:\n$archive");
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
// if archive doesn't exist, stop.
elseif(!(ck_archive($archive))){
  echo "Stopping, can't find archive\n";
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

//echo "DBG->MAIN:\$fpath is:$fpath\n";
//echo "DBG->MAIN:\$foldr_path is:$foldr_path\n";

//$folder_cache = array();

echo
"Working on uploading archive:\n$archive\ninto folder path $foldr_path\n";
echo "Using folder file name $folder to store the archive in\n";

$folder_path = explode('/', $foldr_path);
//echo "Folder path\n";
//print_r($folder_path);

echo "Checking folder path $foldr_path for existence\n";
// determine what folders exist

$folder_cache = get_fpath_keys($folder_path);

// create any that don't exist and add the folder_pk for that folder.

$folder_cache = create_folders($folder_cache, $folder_path);

//echo "DBG->MAIN: after create folders folder_cache is:\n";
//print_r($folder_cache);

if (ck_4_upload($folder_cache, $folder)){
  echo
    "Warning: $folder has already been uploaded into the folder path\n$foldr_path, Skipping...\n\n";
  exit(1);
}

// Get the folder_fk of the last entry in the folder_cache, this should
// be the last folder (leaf). end returns the value in an associative array.

$folder_fk = end($folder_cache);

//pdbg("Main: folder before upload:$folder");
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
 * @version 0.3
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
 * @version 0.3
 */

function ck_4_upload($folder_cache, $folder_name){

  // Check to see if the folder has an upload_pk.  If it doesn't
  // then there has never been an upload in the folder.
  // If there is an upload record, we emit a warning and stop processing
  // that archive

  $folder_pk = end($folder_cache);
  //  pdbg("CK4U: \$folder_pk is:$folder_pk");

  $sql_up =
  "select name, upload_pk from leftnav where parent=$folder_pk and foldercontents_mode=2";

  $uploaded  = db_queryall($sql_up);
  //  pdbg("CK4U: \$uploaded is:", $uploaded);
  $rows = count($uploaded);

  // check rows, 0 = no upload rec

  if ($rows == 0){
    return(false);
  }
  elseif ($rows >= 1){
    //    pdbg("CK4U: \$folder_name is:$folder_name");
    // check to see if this name has been uploaded before
    // If we find it we return true, else false.
    for ($i=0; $i< $rows; $i++){
      $upload_name = $uploaded[$i]['name'];
      if ($upload_name == $folder_name){
        // if there is an upload_pk, we have already loaded this....
        $upk = $uploaded[$i]['upload_pk'];
        if ($upk == true){
          return(true);
        }
      }
    }
  }
  return(false);
}


/**
 * function:create_folder
 *
 * create a folder in the db.  Assumes that the folder does NOT exist.
 * The caller should first verify that the folder doesn't exist.
 * Use folder_exists to determine folder existence.
 *
 * @param integer $parent_fk parent foreign key
 * @param string  $folder_name The name you want to give to the folder
 * @param string  $description Short description of the folders purpose
 *
 * Returns: associative array with folder as key and folder_pk as value.
 *
 * @author mark.donohoe@hp.com
 * @version 0.3
 *
 */

function create_folder($parent_fk, $folder_name, $description){

  // This used to be a useful function.  See if you can just enhance
  // create_folder in the db_postgres lib and get rid of this pos.

  echo "Creating Folder $folder_name associated with parent key:$parent_fk\n";
  // ask bob about the difference between this and whats in folder_exists.

  $fc_pk = createfolder($parent_fk, $folder_name, $description);
  // Createfolder returns the foldercontents pk, not the folder pk.  Go get it.
  $sql = "Select folder_pk from leftnav where
                  parent='$parent_fk' and foldercontents_mode=1 
                  and name='$folder_name'"; 
  $folder_fk = db_query1($sql);
  $name_and_key[$folder_name] = $folder_fk;
  return($name_and_key);
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
 * Returns: associative array with folder as key and folder_pk as value.
 *
 * @author mark.donohoe@hp.com
 * @version 0.3
 *
 */
function create_folders($folder_cache, $folder_path){

  //pdbg("CF: On entry, folder_cache is:",$folder_cache);

  // used to determine if we are at the 1st folder.
  $cache_key = key($folder_cache);

  for ($fpi=0; $fpi<count($folder_cache); $fpi++){
    // top folder is a special case
    //pdbg("CF:\$folder_cache[$folder_path[$fpi]]:{$folder_cache[$folder_path[$fpi]]}");
    //pdbg("CF:\$folder_path[$fpi] is:$folder_path[$fpi]");
    if ($folder_path[$fpi] == $cache_key){
      //      echo "DBG->CF: checking top folder $folder_path[$fpi]\n";
      //      print_r($folder_cache[$folder_path[$fpi]]);
      //      echo "\n";
      if ($folder_cache[$folder_path[$fpi]] === false){
        //	echo "DBG->CF: Calling create_parent\n";
        $fstat = create_parent($folder_path[$fpi]);
        if(! $fstat[$folder_path[$fpi]]){
          echo "Could not create folder $folder_path[$fpi]\n";
          echo "ERROR: Unrecoverable error stopping\n";
          exit(1);
        }
        // update the cache
        else{
          //echo "DBG->CF: Updating cache\n";
          $folder_cache[$folder_path[0]] = $fstat[$folder_path[0]];
          continue;
        }
      }
      continue;
    }
    if ($folder_cache[$folder_path[$fpi]] === false){
      //      echo "DBG->CF:\$folder_cache[$folder_path[$fpi]] was false\n";
      $fstat = create_folder(
      $folder_cache[$folder_path[$fpi-1]], $folder_path[$fpi], '');
      if(! $fstat[$folder_path[$fpi]]){
        echo "Could not create folder $folder_path[$fpi]\n";
        echo "ERROR: Unrecoverable error stopping\n";
        exit(1);
      }
      // update the cache
      else{
        //echo "DBG->CF: Updating cache\n";
        //	echo "\$folder_cache[$folder_path[$fpi]] = \$fstat[$folder_path[$fpi]]\n";
        $folder_cache[$folder_path[$fpi]] = $fstat[$folder_path[$fpi]];
      }
    }
  }

  //  echo "DBG->CF: after create folders folder_cache is:\n";
  //  print_r($folder_cache);

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
 * Returns: associative array with folder as key and folder_pk as value.
 *
 * @todo think about combining with create_folder
 *
 * @author mark.donohoe@hp.com
 * @version 0.3
 *
 */

function create_parent($folder){

  // HACK: the description is sorta lame... not sure what else to use
  // NOTE: this function will probably have to change when users are
  // implimented.

  // all parent folders are created as a child of the root folder for
  // the user (1st release only has one user....)

  echo "Creating Parent Folder:$folder\n";
  $desc = "Parent folder $folder";
  $sql = 'select root_folder_fk from users limit 1';
  $rfolder4user = db_query1($sql);
  $fc_pk = createfolder($rfolder4user, $folder, $desc);
  // Createfolder returns the foldercontents pk, not the folder pk.  Go get it.
  $sql = "Select folder_pk from leftnav where
                  parent='$rfolder4user' and foldercontents_mode=1 
                  and name='$folder'"; 
  $parent_pk = db_query1($sql);
  $name_and_key[$folder] = $parent_pk;
  return($name_and_key);
}

/**
 * function: get_fpath_keys
 *
 * Get the folder path keys.
 *
 * Given an array of folder names, determine which ones exist.
 *
 * Returns: associative array with folder name as the key and folder_pk as
 *          the value.  If there is no folder_pk, then the folder doesn't
 *          exist and the value is false.
 *
 * @author mark.donohoe@hp.com
 * @version 0.3
 *
 */
function get_fpath_keys($folder_path){

  if (empty($folder_path)){
    echo "DBG->GFK: returning due to empty input\n";
    return(false);
  }
  //  echo "DBG->get_fpath_keys: \$folder_paht on entry is:\n";
  //  print_r($folder_path);
  //  echo "\n";

  $folder_cache = array();
  // process the 1st entry specially, as it must be associated with the
  // root.
  $sql = 'select root_folder_fk from users limit 1';
  $rfolder4user = db_query1($sql);
  $sql_folderP = "Select folder_pk from leftnav where
                  parent='$rfolder4user' and foldercontents_mode=1 
                  and name='$folder_path[0]'";

  // If the query below returns nothing, then the folder doesn't exist.
  $folder_exists_fk = db_query1($sql_folderP);

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

    return($folder_cache);
  }
  // now process the rest of the path... Note the array index ($ai)
  // starts at the second array entry.
  $folder_path_size = count($folder_path);
  for ($ai=1; $ai < $folder_path_size; $ai++){
    $parent_fk = $folder_cache[$folder_path[$ai-1]];
    //    echo "DBG->GFPK:Parent fk is:$parent_fk\n";
    $sql_folder = "Select folder_pk from leftnav where
                   name='$folder_path[$ai]' and parent=$parent_fk";
    //    echo "DBG->GFPK: Query: Select folder_pk from leftnav where name='$folder_path[$ai]' and parent=$parent_fk\n";
    //    echo "DBG->GFPK: Getting folder_pk\n";

    $folder_exists = db_query1($sql_folder);

    //    echo "DBG->GFPK: folder_pk is:";
    //    print_r($folder_exists);
    //    echo "\n";
    if ($folder_exists){
      $folder_cache[$folder_path[$ai]] = $folder_exists;
    }
    else {
      echo "setting remaining entries to false\n";
      for ($start_here = $ai; $start_here < $folder_path_size; $start_here++){
        $folder_cache[$folder_path[$start_here]] = false;
      }
      break;
    }
  }
  //  echo "DBG->get_fpath_keys: \$folder_cache is:\n";
  //  print_r($folder_cache);
  //  echo "\n";
  return($folder_cache);
}


/**
 * function: process_directory
 *
 * process a directory entry, uploading all files and links in the directory.
 * Unless recursion is specified (-r), subdirectories are not processed.
 *
 * Variable number of parmeters:
 * 2 parameters: parent-path, dir-path: process the dir, skip sub-dirs.
 * 3 parameters: parent-path, dir-path -r: process the dir, process all
 *   entries under dir.
 *
 */
function process_directory(){

  echo "process_directory not yet implimented";
  return(true);
}

/**
 * function: read_parms_file
 *
 * Read a file of input parameters and act on them
 *
 * @param string $parms_file fully qualified path to input file
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
    $dummy = exec("./p.sh $rline",$parms, $retval);
    //pdbg("after shell call, \$parms is",$parms);

    $pcount = count($parms);
    for($p=0; $p<$pcount; $p++){
      //    echo "\$parms[$p]is:$parms[$p]";
      $token = rtrim($parms[$p]);
      $token = ltrim($token);
      //pdbg("\$token is:$token \$p is:$p");
      switch($token){
        case '-A':
          //pdbg("Matched -A");
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
          //      pdbg("Matched -n");
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
    //pdbg("RFP: FP:$fpath FN:$folder_name\nA:$archive\nAA:$cap_a W:$fetch_url D:$desc\n\n");
    // verify input parameters
    // if the description is null (empty), put default in: Future enhancement
    //    pdbg("RPF: Out of while loop, checking archive");

    // It's either a url or an archive
    // if archive doesn't exist, don't create folders, skip to next line in
    // input file. Same if the wget does not succeed.
    if($fetch_url){
      //pdbg("RP: processing url \$archive is:\n$archive");
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
    //    echo "DBG->RPF: Folder path\n";
    //    print_r($folder_path);

    $folder_cache = get_fpath_keys($folder_path);

    //pdbg("RPF: \$folder_cache is:", $folder_cache);

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

function upload_archive($folder_fk, $folder_name, $description, $archive){

  // checking for a good archive occurs before this is called.

  global $AGENTDIR;

  $upload_fk =
  createuploadrec($folder_fk, $folder_name, $description, $archive, 1<<4);
  // This saves the archive.  Set to NULL to have the archive removed.
  // $keep = NULL;
  $keep = 1;                     // Set for testing
  // webgoldimport does not use the $folder_name, pass in ''

  $cmd = "$AGENTDIR/webgoldimport $upload_fk $archive '' $keep 2>&1";

  #  echo "DBG: will run:\n$cmd\n";

  $lastline = exec($cmd, $out, $retval);
  #  echo "lastline is:$lastline\n";

  if($retval != 0) {
    echo "ERROR: could not run webgoldimport, return code is:$retval\n";
    return(false);
  }

  $jobQ = job_create_unpack($upload_fk, $archive, '');
  if(isset($jobQ)){
    job_create_defaults($upload_fk, $jobQ);
  }
  else {
    echo "INTERNAL ERROR! job_create_unpack did not return a jobq:$jobQ\n";
    return(false);
  }
  return(true);
}

?>
