#!/usr/bin/php
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
 * Report fossology results
 *
 * @param date? all? ??
 *
 * @return
 *
 * @version "$Id: $"
 *
 * Created on Nov 10, 2008
 */

// 1. move files based on date from tmp to public_html (for now...)
// 2. gather stats from each file
// 3. assign data to form
// 4. display form
/*
 * things to think about:
 * - how to just 'add to' the table, so it just grows....
 *   -   generate a new template each time: 'current-report' and past-
 * report or use the date in the report... leave a last report file?
 * - use smarty's ability to include files, so have a past-data and
 * a current data and include them in the template which is the table
 * itself....?
 */

list ($me, $file) = $argv;

if (empty ($file))
{
  print "usage: $me filepath\n";
}

print "starting to process $file\n";

$res = fileReport($file);
//print "we got:\n"; print_r($res) . "\n";

// results are in sets of 3

$resSize = count($res);
for ($suite=0; $suite <= $resSize; $suite += 3)
{

  if(($suite+2) > $resSize) { break; }

  //print "suite is:$suite\n";
  $suiteName = parseSuiteName($res[$suite]);
  print "parsed suite name:$suiteName\n";

  $m = parseResults($res[$suite+1]);
  print "resutlts are:$m\n";
  //print_r($m) . "\n";

  $t = parseElapseTime($res[$suite+2]);
  print "The elapse time was:$t\n\n";


}

/*
$suiteName = parseSuiteName($res[0]);
print "parsed suite name:$suiteName\n";

$m = parseResults($res[1]);
print "resutlts are:\n";
print_r($m) . "\n";

$t = parseElapseTime($res[2]);
print "The elapse time was:$t\n";
*/
/**
 * fileReport
 *
 * read a file and return the number of passes, failures and
 * exceptions, elapse time?
 */

/*
 * pattern is Starting < > Tests ... data followed by either OK or
 * FAILURES! then results, skip a line then elapse time.
 */

function fileReport($file)
{
  $started = 0;
  $resultLines = array ();
  if (empty ($file))
  {
    return FALSE;
  }
  $FD = fopen($file, 'r');
  while ($line = fgets($FD, 1024))
  {
    if (preg_match('/^Starting/', $line))
    {
      if (!$started)
      {
        array_push($resultLines, $line);
        $started = 1;
      }
    }
    elseif (preg_match('/^OK/', $line) || preg_match('/^FAILURES/', $line))
    {
      $line = fgets($FD, 1024);
      array_push($resultLines, $line);
      $tossme = fgets($FD, 1024);
      $line = fgets($FD, 1024);
      array_push($resultLines, $line);
      $started = 0;
    } else
    {
      continue;
    }
  }
  return ($resultLines);
}

/**
 * parseSuiteName
 *
 * parse a line of text, return the 2nd and 3rd token as a hyponated
 * name.
 *
 * @param string $string the string to parse
 *
 * @return boolean (false or a string)
 */
function parseSuiteName($string)
{
  if (empty ($string))
  {
    return (FALSE);
  }
  $pat = '^Starting\s(.*?)\sat:';
  $matches = preg_match("/$pat/", $string, $matched);
  //print "matched is:{$matched[1]}\n";
  return($matched[1]);
}

/**
 * parseResults
 *
 * parse a line of text that represents simpletest test result line.
 * Return an associative array with passes, failures and exceptions as
 * the keys,
 *
 * @param string $string the string to parse
 */
function parseResults($string)
{
  if (empty ($string))
  {
    return (FALSE);
  }
  //$pat = '.*?(Passes):(.*?).\s(Failures):\s(.*?).+(Exceptions):\s(.*)';
  $pat = '.*?(Passes):\s(.*?),\s(Failures):\s(.*?),\s(Exceptions):\s(.*)';
  $matches = preg_match("/$pat/", $string, $matched);
  $results = array ();
  if ($matches)
  {
    $results[$matched[1]] = $matched[2];
    $results[$matched[3]] = $matched[4];
    $results[$matched[5]] = $matched[6];
    $res = $matched[2] . ":" . $matched[4] . ":" . $matched[6];
  }
  //return ($results);
  return ($res);
}

/**
 * parseElapseTime
 *
 * Given a string that represents the elapse time printed by the
 * fossology tests, parse it and return a string in the form hh:mm:ss.
 *
 * @param string $string
 * @return boolean (string or false)
 */
function parseElapseTime($string)
{
  if (empty ($string))
  {
    return (FALSE);
  }
  //$pat = '.*?took.(.?).minute.+(.?).seconds';
  $pat = '.*?took.(.?).minute.*?\s(.?)\s';
  $matches = preg_match("/$pat/", $string, $matched);
  //$time = 'infinity';
  if ($matches)
  {
    $time = '00h:' . $matched[1] . 'm:' . $matched[2] . 's';
  }
  return ($time);
}
?>
