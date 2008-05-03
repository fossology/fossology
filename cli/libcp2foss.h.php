<?php
/***********************************************************
 libcp2foss.h.php
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
 ***********************************************************//**

/**
* Library of functions that help cp2* do their job.
*
* @package libcp2foss.h.php
*
* @author mark.donohoe@hp.com
* @version $Id$
*
*/

/**
 * function: CreateFolder
 *
 * Given a parent folder, create the folder with the optional description
 *
 * @param int    $parent_fk the parent key
 * @param string $folder_name the name of the folder to create
 * @param string $description Brief descrition of the folders use
 *
 * @return an associative array folder name as the key and folder_pk as
 * the value.
 *
 */

function CreateFolder($parent_key, $folder_name, $description="") {
  global $DB;

  /* Check the name */
  $folder_name = trim($folder_name);
  if (empty($folder_name)) { return(FALSE); }

  /* Make sure the parent folder exists */
  $Results = $DB->Action("SELECT * FROM folder WHERE folder_pk = '$parent_key';");
  $Row = $Results[0];
  if ($Row['folder_pk'] != $parent_key)
  {
    // parent doesn't exist
    return(FALSE);
  }

  // folder name exists under the parent?
  $Sql = "SELECT * FROM leftnav WHERE name = '$folder_name' AND
                parent = '$parent_key' AND foldercontents_mode = '1';";
  $Results = $DB->Action($Sql);
  if ($Results[0]['name'] == $folder_name) { return(0); }

  /* Create the folder
   * Block SQL injection by protecting single quotes
   *
   * Protect the folder name with htmlentities.
   */
  $folder_name = str_replace("'", "''", $folder_name);  // PostgreSQL quoting
  $description = str_replace("'", "''", $description);            // PostgreSQL quoting
  $DB->Action(
  "INSERT INTO folder (folder_name,folder_desc) VALUES ('$folder_name','$description');");
  $Results = $DB->Action(
  "SELECT folder_pk FROM folder WHERE folder_name='$folder_name' AND folder_desc = '$description';");
  $FolderPk = $Results[0]['folder_pk'];
  if (empty($FolderPk)) { return(FALSE); }

  $DB->Action("INSERT INTO foldercontents (parent_fk,foldercontents_mode,child_id) VALUES ('$parent_key','1','$FolderPk');");

  $name_and_key[$folder_name] = $FolderPk;

  return($name_and_key);
}

/**
 * function: hash2bucket
 *
 * Returns the folder name to place the archive in.
 *
 * The folder name returned will be in the form x-x. For example, a-c.
 * If the name of the project does not start with an alpha, it will
 * be placed in the 'Other' directory.
 *
 * Both lower and upper case letters map to the same bucket.
 * e.g. A => a-c, c => a-c
 *
 * @param string $name name of the project
 *
 */

function hash2bucket($name){

  // convert to lower case, both upper and lower letters map to the
  // same alpha-group e.g. A => a-c, c => a-c

  $lc_name = strtolower($name);

  $map = array('a' => 'a-c',
               'b' => 'a-c',
               'c' => 'a-c',
               'd' => 'd-f',
               'e' => 'd-f',
               'f' => 'd-f',
               'g' => 'g-i',
               'h' => 'g-i',
               'i' => 'g-i',
               'j' => 'j-l',
               'k' => 'j-l',
               'l' => 'j-l',
               'm' => 'm-o',
               'n' => 'm-o',
               'o' => 'm-o',
               'p' => 'p-r',
               'q' => 'p-r',
               'r' => 'p-r',
               's' => 's-u',
               't' => 's-u',
               'u' => 's-u',
               'v' => 'v-z',
               'w' => 'v-z',
               'x' => 'v-z',
               'y' => 'v-z',
               'z' => 'v-z'
               );

               // return 'Other' if the name starts with a non-alpha char.
               $dir = $map[substr($lc_name,0,1)];
               if (isset($dir)){
                 return($dir);
               }
               else {
                 return('Other');
               }

}

/**
 * function: suckupfs
 *
 * process a directory entry, tar up all files in the directory.
 * Unless recursion is specified (-R), subdirectories are not processed.
 * If recursion is specified, everything under the directory is tared up
 *
 * @param string $path directory path to upload
 * @param string $recurse flag to indicate recursion is on.  Default is
 * off.
 *
 * @return string $tpath the path to the tar archive, or false
 *
 */

function suckupfs($path, $recursion = false){

  $cwd       = '.';
  $parentdir = '..';
  $dirs      = array();
  $other     = array();
  $dir_parts = pathinfo($path);

  // check if the file exists (can you reach it? in the case of multiple
  // agent machines).

  if( ! file_exists($path))
  {
    return(FALSE);
  }

  $SPATH = opendir($path)
  or die("Suckupfs: Can't open: $path $php_errormsg\n");

  while ($dir_entry = readdir($SPATH)){
    //  echo "\$dir_entry is:$dir_entry\n";
    if ($dir_entry == $cwd || $dir_entry == $parentdir) {
      //echo ("skipping $dir_entry\n");
      continue;
    }
    $check = "$path" . '/' . "$dir_entry";
    if(is_dir("$check")){
      $dirs[] = $dir_entry;
    }
    elseif(is_file("$check")){
      $files[] = $dir_entry;
    }
    else {
      $other[] = $dir_entry;
    }
  }
  closedir($SPATH);

  /*
   * tar will suck up a sub dir and it's contnents, so must
   * not supply them in list to tar if -r is not turned on.
   *
   * Always put the files in the list.  If recursion is on, put everything
   * else in the list as well.  Return the path to the tar file.
   */
  foreach($files as $file){
    $flist .= "$file ";
  }
  if($recursion){
    foreach($dirs as $ditem){
      $flist .= "$ditem ";
    }
    foreach($other as $item){
      $flist .= " $item";
    }
  }
  chdir($path) or die("Can't cd to $path, $php_errormsg\n");
  $ftail = getmypid();
  if(empty($ftail)){
    $ftail = session_id();
  }
  $tpath = '/tmp/' . "{$dir_parts['basename']}" . '.tar.bz2.' . "$ftail";
  $tcmd = "tar -cjf $tpath --exclude='.svn' --exclude='.cvs' $flist 2>&1";
  $last = exec($tcmd, &$tossme, &$rtn);
  // Tar almost never returns 0!  So if it's not 0, then check existence
  // and size.
  if ($rtn >= 0){
    //echo "\$tpath is:$tpath\n";
    if(!(filesize($tpath))){
      //echo "ERROR: filesize returned False\n";
      return(FALSE);
    }
    else {
      return($tpath);
    }
  }
}

/**
 * function: wget_url
 *
 * wget a url and check for erorrs.
 *
 * This function uses tmp files, that will be removed when the
 * calling code exits.  The use of this fuction is for getting something
 * that will be uploaded, hence no need to keep the files around.
 *
 * @param string $url the url to get.
 * @return path to file(true) or false on wget failure
 */

function wget_url($url){

  // items we need
  $proxy = "export http_proxy='web-proxy.fc.hp.com:8088'; ";
  $options = '--no-check-certificate';

  // tmp files
  if (!($wget_logfile = tempnam('/tmp', 'wgetlog-cp2-'))){
    echo "ERROR! could not create tmp file, $php_errormsg\n";
    return false;
  }
  if (!($archive_tmp = tempnam('/tmp', 'cp2-archive-'))){
    echo "ERROR! could not create/tmp file, $php_errormsg\n";
    return false;
  }

  $wCmd .= "$proxy" . "wget $options -O $archive_tmp -o $wget_logfile '$url' ";
  //pdbg ("\$wCmd is:\n$wCmd");
  exec("$wCmd", $dummy, $retval);
  //pdbg("Wget return code is:$retval");
  if ($retval != 0){
    echo "ERROR: wget returned non-zero:$retval\n";
    echo "See the file /tmp$wget_tmpfile for details\n";
    return false;
  }
  if(!ck4errors($wget_logfile)){
    echo "ERROR: wgetreturned 0, but there were web errors\n";
    echo "See the file /tmp/$wget_tmpfile for details\n";
    return false;
  }
  return $archive_tmp;
}

/**
 * function: ck4errors
 *
 * Check for errors in the wget log file
 *
 * This function checks for various types of errors in a wget log file.
 * Wget can return a 0 status in the face of 404 web type errors.  This
 * function checks for those.
 *
 * @param string $wget_logfile path to wget log file to examine.
 *
 * @return true if no errors false if errors are found
 */

function ck4errors ($wget_logfile){

  $contents = file($wget_logfile);
  $size = count($contents);
  $stat_line = $contents[$size-2];
  if(ereg('^Removed ',$stat_line)){
    // adjust for a different case, if wget downloads a .listing file
    // it adjusts it be an index.html file instead.
    $stat_line = $contents[$size-1];
  }
  //pdbg("_GFMP: Stat line is:\n$stat_line");
  // We shouldn't find errors like this in the file, wget is supposed to
  // have returned with 0 status.
  if (ereg('ERROR 404:', $stat_line)){
    echo "ERROR 404 found in file $dir_entry\n";
    echo "Line was:\n$stat_line\n";
    return(false);
  }
  elseif (ereg('ERROR 502:', $stat_line)){
    echo "ERROR 502 found in file $dir_entry\n$stat_line\n";
    echo "Line was:\n$stat_line\n";
    return(false);
  }
  elseif (ereg('ERROR 503:', $stat_line)){
    echo "ERROR 503 found in file $dir_entry\n$stat_line\n";
    echo "Line was:\n$stat_line\n";
    return(false);
  }
  elseif (ereg('ERROR 400:', $stat_line)){
    echo "ERROR 400 found in file $dir_entry\n$stat_line\n";
    echo "Line was:\n$stat_line\n";
    return(false);
  }
  return(true);
}