<?php
/*
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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
 */
/**
 *  allFilePaths
 *
 *  given a directory, iterate through it and all subdirectories returning
 *  the absolute path to the files.
 *
 * created: May 22, 2009
 */

//ldir = '/home/markd/Eddy';
//$ldir = '/home/fosstester/regression/license/eddy/GPL';
/**
 * allFilePaths
 *
 * given a directory, iterate through it and all subdirectories returning
 * the absolute path to the files.
 *
 * @param string $dir the directory to start from either an absolute path or
 * a relative one.
 *
 * @return array $fileList a list of the absolute path to the files or empty
 * array on error.
 */
function allFilePaths($dir) {

  $fileList = array();
  if(empty($dir)) {
    return($fileList);  // nothing to process, return empty list.
  }
  try {
    foreach(new recursiveIteratorIterator(
    new recursiveDirectoryIterator($dir)) as $file) {
      $fileList[] = $file->getPathName($file);
    }
    return($fileList);
  }
  /*
   * if the directory does not exist or the directory or a sub directory
   * does not have sufficent permissions for reading return an empty list
   */
  catch(Exception $e) {
    print $e->getMessage();
    return(array());
  }
}

/**
 * escapeDots($string)
 *
 * Escape '.' in a string by replacing '.' with '\.'
 * @param string $string the input string to escape.
 * @return string $estring the escaped string or False.
 */
function escapeDots($string)
{
  if (empty ($string))
  {
    return (FALSE);
  }
  $estring = preg_replace('/\./', '\\.', $string);
  //print  "ED: string is:$string, estring is:$estring\n";
  if ($estring === NULL)
  {
    return (FALSE);
  }
  return ($estring);
}

/**
 * public function getHost
 *
 * returns the host (if present) from a URL
 *
 * @param string $URL a url in the form of http://somehost.xx.com/repo/
 *
 * @return string $host the somehost.xx.com part is returned or NULL,
 * if there is no host in the uri
 *
 */

function getHost($URL)
{
  if (empty ($URL))
  {
    return (NULL);
  }
  $found = parse_url($URL, PHP_URL_HOST);
  //print "DB: getHost: url is:$URL\nafter parse, found is:$found\n";
  return ($found);
} // getHost

/**
 * getMailSubjects
 *
 * Check to see if there is new mail for the user
 *
 * NOTE: must be run by the user who owns the system mailbox in /var/mail
 *
 * @return array Subjects, list of Fossology subjects that match.  On error,
 * the first entry in the array will start with the string 'ERROR!'
 *
 */
function getMailSubjects() {
  /*
   * use preg_match, but the test must be run by the user who owns the email file
   * in /var/mail.
   */
  $MailFile = "/var/mail/";

  //$user = get_current_user();
  $user = exec('id -un', $out, $rtn);
  $UserMail = $MailFile . $user;
  if(file_exists($UserMail) === FALSE) {
    return(array("ERROR! $UserMail does not exist"));
  }
  $FH = fopen($UserMail,'r');
  if($FH === FALSE) {
    return(array("ERROR! Cannot open $UserMail"));
  }
  while (! feof($FH)){
    $line = fgets($FH);
    $matched = preg_match('/Subject:\sFOSSology Results.*?$/',$line, $matches);
    if($matched) {
      $Subjects[] = $line;
    }
  }
  return($Subjects);
} //getMailSubjects

function lastDir($dirpath) {
  // can't have a tailing slash, remove it if there
  $dirpath = rtrim($dirpath, '/');
  $directories = explode('/',$dirpath);
  return(end($directories));
}

/**
 * makeUrl($host,$query)
 *
 * Make a url from the host and query strings.
 *
 * @param $string $host the host (e.g. somehost.com, host.privatenet)
 * @param $string $query the query to append to the host.
 *
 * @return the http string or NULL on error
 */
function makeUrl($host, $query) {
  if (empty ($host)) {
    return (NULL);
  }
  if (empty ($query)) {
    return (NULL);
  }
  return ("http://$host$query");
}

function filesByDir($dir) {

  $ByDir = array();
  $fileList = array();
  if(empty($dir)) {
    return($fileList);  // nothing to process, return empty list.
  }
  $curDir = lastDir($dir);
  print "DB: at the top curDir is:$curDir\n";
  try {
    //foreach(new recursiveIteratorIterator(new recursiveDirectoryIterator($dir)) as $file) {
    $dirObjects = new recursiveIteratorIterator(new recursiveDirectoryIterator($dir),RecursiveIteratorIterator::SELF_FIRST);
    // dirobjs is recusiveIteratorIterator object
    foreach($dirObjects as $name => $obj) {
      print "name is:$name\n";
      //print "obj is:$obj\n";
      continue;
      $f = new recursiveDirectoryIterator($dir);
      $sb = $f->getSubPath();
      $sbn = $f->getSubPathName();
      print "DB: subpath is:$sb\n";
      print "DB: subpathname is:$sbn\n";
      $dirpath = $file->getPath();
      print "DB: dirpath is:$dirpath\n";
      /*
       if($dirpath !== $dir) {
       //filesByDir($dirpath);
       $dirs = explode('/',$dirpath);
       $last = end($dirs);
       $curDir = prev($dirs);
       print "DB: (prev) adjusted curDir is:$curDir\n";
       }
       */
      print "DB: dirpath is:$dirpath\n";
      $lastDir = lastDir($dirpath);
      //print "DB: lastDir is:$lastDir\n";
      if($curDir == $lastDir) {
        $fileList[] = $file->getFilename();
      }
      else {
        /*
         The check below is for the first time through the loop.  If the check
         is not there, then the first entry in the final array is always empty.
         */
        if(empty($fileList)) {
          $curDir = $lastDir;
          continue;
        }
        print "DB: dir is:$dir\n";
        $dir = rtrim($dir, '/');
        print "DB: in loop curDir is:$curDir\n";
        $key = $dir . "/$curDir";
        print "DB: key is:$key\n";
        $ByDir[$key] = $fileList;
        $curDir = $lastDir;
        $fileList = array();
        // add the current entry in
        $fileList[] = $file->getFilename();
      }
    }
    return($ByDir);
    }

    /*
     if the directory does not exist or the directory or a sub directory
     does not have sufficent permissions for reading return an empty list
     */
    catch(Exception $e) {
      //print "in exception!\n$e\n";
      return(array());
    }
  } // fileByDir
  ?>
