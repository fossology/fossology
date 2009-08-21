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
      //print "file is:$file\n";
      $fileList[] = $file->getPathName($file);
    }
    //print "Fossology Results are:\n";print_r($fileList) . "\n";
    return($fileList);
  }
  /*
   * if the directory does not exist or the directory or a sub directory
   * does not have sufficent permissions for reading return an empty list
   */
  catch(Exception $e) {
    //print "in exception!\n$e\n";
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
function makeUrl($host, $query)
{
  if (empty ($host))
  {
    return (NULL);
  }
  if (empty ($query))
  {
    return (NULL);
  }
  return ("http://$host$query");
}
?>
