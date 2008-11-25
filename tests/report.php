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

// put full path to Smarty.class.php
require_once('/usr/share/php/smarty/libs/Smarty.class.php');

$smarty = new Smarty();

$smarty->template_dir = '/home/markd/public_html/smarty/templates';
$smarty->compile_dir = '/home/markd/public_html/smarty/templates_c';
$smarty->cache_dir = '/home/markd/public_html/smarty/cache';
$smarty->config_dir = '/home/markd/public_html/smarty/configs';

/*
list ($me, $file) = $argv;

if (empty ($file))
{
  print "usage: $me filepath\n";
  exit(0);
}
*/

$file = '/tmp/AllFOSSologyTests-2008-11-24';
//rint "starting to process $file\n";

$res = fileReport($file);
//print "we got:\n"; print_r($res) . "\n";

// results are in sets of 3

$resSize = count($res);
$results = array();
for ($suite=0; $suite <= $resSize; $suite += 3)
{
  if(($suite+2) > $resSize) { break; }

  //print "suite is:$suite\n";
  $suiteName = parseSuiteName($res[$suite]);
  array_push($results, $suiteName);
  //print "parsed suite name:$suiteName\n";

  $pfe_results = parseResults($res[$suite+1]);
  $pfe = split(':',$pfe_results);
  array_push($results, $pfe[0]);
  array_push($results, $pfe[1]);
  array_push($results, $pfe[2]);
  //print "resutlts are:$results\n";

  $etime = parseElapseTime($res[$suite+2]);
  array_push($results, $etime);
  //print "The elapse time was:$etime\n\n";
}
$cols = 5;
$smarty->assign('results',$results);
$smarty->assign('cols',$cols);
$smarty->display('1run-report.tpl');

/**
 * fileReport
 *
 * read a file and return the number of passes, failures and
 * exceptions, elapse time.
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
 * parse a line of text, return the 2nd and 3rd token as a hyphonated
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
  $parts = array();
  $pat = '.+took\s(.*?)\sto\srun$';
  $matches = preg_match("/$pat/", $string, $matched);
  //print "the array looks like:\n"; print_r($matched) . "\n";
  $parts = split(' ', $matched[1]);
  //print "split array looks like:\n"; print_r($parts) . "\n";
  //$time = 'infinity';
  $sizep = count($parts);
  $etime = NULL;
  for($i=0; $i<$sizep; $i++)
  {
   $etime .= $parts[$i] . substr($parts[$i+1],0,1) . ":";
   $i++;
  }
  $etime = rtrim($etime, ':');
  return ($etime);
}
?>
