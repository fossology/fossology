<?php

/***********************************************************
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
 ***********************************************************/

/**
 * getMailSubjects
 *
 * Check to see if there is new mail for the user
 *
 * NOTE: must be run by the user who owns the system mailbox in /var/mail
 *
 * @return array Subjects, list of Fossology subjects that match.
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
  $FH = fopen($UserMail,'r') or die ("Cannot open $UserMail, $phperrormsg\n");
  while (! feof($FH)){
    $line = fgets($FH);
    $matched = preg_match('/Subject:\sFOSSology Results.*?$/',$line, $matches);
    if($matched) {
      $Subjects[] = $line;
    }
  }
  return($Subjects);
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
}

/**
 * getBrowserUri($name, $page)
 *
 * Get the url fragment to display the upload from the xhtml page.
 *
 * @param string $name the name of a folder or upload
 * @param string $page the xhtml page to search
 *
 * TODO: finish or scrap this method
 *
 * @return $string the matching uri or null.
 *
 */
function getBrowseUri($name, $page)
{
  //print "DB: GBURI: page is:\n$page\n";
  //$found = preg_match("/href='(.*?)'>($uploadName)<\/a>/", $page, $matches);
  // doesn't work: '$found = preg_match("/href='(.*?)'>$name/", $page, $matches);
  $found = preg_match("/href='((.*?)&show=detail).*?/", $page, $matches);
  //$found = preg_match("/ class=.*?href='(.*?)'>$name/", $page, $matches);
  print "DB: GBURI: found matches is:$found\n";
  print "DB: GBURI: matches is:\n";
  var_dump($matches) . "\n";
  if ($found)
  {
    return ($matches[1]);
  } else
  {
    return (NULL);
  }
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
