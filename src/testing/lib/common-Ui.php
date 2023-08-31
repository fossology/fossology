<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
* \brief library of ui test functions that are used by the test infrastructure.
*
* The simpletest UI tests use this library.
*
* @version "$Id$"
*
* Created on Aug 17, 2011 by Mark Donohoe
*/

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
  $host = parse_url($URL, PHP_URL_HOST);
  //print "DB: getHost: url is:$URL\nafter parse, found is:$found\n";
  /*
  * if the host is localhost, this won't work, so we go get the real
  * host name.  This is due to the fact that on a server where
  * the db and and scheduler are on the same system, the Db.conf
  * file can have localhost for the hostname.
  */
  if ($host == 'localhost')
  {
    $realHost = exec("hostname -f", $out, $rtn);
    if($rtn == 0)
    {
      $host = $realHost;
    }
  }
  return ($host);
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
  $Sujects = array();

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
 * @param string $host the host (e.g. somehost.com, host.privatenet)
 * @param string $query the query to append to the host.
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
