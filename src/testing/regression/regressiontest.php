#!/usr/bin/php
<?php
/*
SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

SPDX-License-Identifier: GPL-2.0-only
*/

/** @file UI Regression test.
 *  Each file in regression/good/ is named with a URL.
 *  Capture that URL output (to regression/output_{pid}/) and compare to good.
 *  POST data is in regression/post/ (not currently implemented)
 *
 *  To Use:
 *  1. mkdir -p regression/good
 *  2. use wget and save result file to regression/good.  The output file name
 *     should look like "?mod=nomoslicense&upload=1&item=1"
 *  3. Run this script.  e.g.:
 *     ./regressiontest.php -w "http://bobg.fc.hp.com/trunk"
 *  4. This will hit the url's found in the good/ and write the pages to the 
 *     "output_{pid}" directory.
 *  5. The program will diff files between the good/ and output_{pid} directories that have the same name.
 *     Any difference is either a regression or an enhancement.  If it is an
 *     enhancement, replace the good/ file with the one from the output directory.
 *  6. Remove the output directory after all diffs have been resolved.
 *
 *  Notes:
 *  1. Some url's have an elapsed time printed.  These plugins should have a &testing=1 parameter
 *     added so that this data doesn't print.
 *  2. Some url's need post data to test.  This program could be enhanced to supply
 *     post data.
 *
 *  Sample Output:
 *   $ ./regressiontest.php -w "http://bobg.fc.hp.com/trunk"
 *   Regression found: Baseline is regression/good//?mod=browse&upload=1&item=1, new content is regression/output_29370/?mod=browse&upload=1&item=1
 *   Total files checked: 3
 *   No regression in: 2
 *   Regression found in: 1
 */

// $DATAROOTDIR and $PROJECT come from Makefile
//require_once "$DATAROOTDIR/$PROJECT/lib/php/bootstrap.php";
require_once "/usr/local/share/fossology/lib/php/bootstrap.php";

$SysConf = array();  // fo system configuration variables
$PG_CONN = 0;   // Database connection

/* Set SYSCONFDIR and set global (for backward compatibility) */
$SysConf = bootstrap();

/* Initialize global system configuration variables $SysConfig[] */
ConfigInit($SYSCONFDIR, $SysConf);

/* Directories */
$GoodDir = "regression/good/";
$PostDir = "regression/post/";
$OutputDirTemplate = "regression/output_";  /* output dir is suffixed with pid */
$OutputDir = $OutputDirTemplate . posix_getpid();

/*  -h webhost
 */
$Options = getopt("hw:");
if ( array_key_exists('h', $Options))
{
  Usage($argc, $argv);
  exit(0);
}

if ( array_key_exists('w', $Options))
{
  $WebHost = $Options['w'];
}
else
{
  $WebHost = "localhost";
}

/* Create directory to put results */
if (mkdir($OutputDir, 0777, true) === false)
{
  echo "Fatal: Could not create $OutputDir\n";
  exit(-1);
}

/* Open $GoodDir */
if (($DirH = opendir($GoodDir)) === false)
{
  echo "Fatal: Could not create $OutputDir\n";
  exit(-1);
}

/* Loop through $GoodDir files */
$FileCount = 0;
$GoodFileCount = 0;
$BadFileCount = 0;
while (($FileName = readdir($DirH)) !== false)
{
  if ($FileName[0] == '.') continue;

  $FileCount++;

  /* $FileName is a URL, hit it and save the results. */
  $URL = $WebHost . "/$FileName";
//echo "URL is $URL\n";
  $ch = curl_init($URL);
  SetCurlArgs($ch);
  $contents = curl_exec( $ch );
  curl_close( $ch );

  /* Save the output in $OutputDir */
  $OutFileName = $OutputDir . "/$FileName";
  if (file_put_contents($OutFileName, $contents) === false)
  {
    echo "Failed to write contents to $OutFileName.\n";
  }

  /* Get the good file contents */
  $GoodFileName = $GoodDir . "$FileName";
  if (($GoodContents = file_get_contents($GoodFileName)) === false)
  {
    echo "Failed to read good contents from $GoodFileName.\n";
  }

  /* compare good and output file contents */
  if ($GoodContents != $contents)
  {
    echo "Regression found: Baseline is $GoodFileName, new content is $OutFileName\n";
    $BadFileCount++;
  }
  else
    $GoodFileCount++;
}

echo "Total files checked: $FileCount\n";
echo "No regression in: $GoodFileCount\n";
echo "Regression found in: $BadFileCount\n";

return (0);

/**
 * @brief Set basic curl args
 * @param $ch  curl handle
 **/
function SetCurlArgs($ch)
{
  global $SysConf;
//  curl_setopt($ch,CURLOPT_USERAGENT,'Curl-php');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
  curl_setopt($ch,
              CURLOPT_HTTPHEADER, array("Content-Type:
               text/html; charset=utf-8"));

  /* parse http_proxy server and port */
  $http_proxy = $SysConf['FOSSOLOGY']['http_proxy'];
  $ProxyServer = substr($http_proxy, 0, strrpos($http_proxy, ":"));
  $ProxyPort = substr(strrchr($http_proxy, ":"), 1);
  if (!empty($ProxyServer))
  {
    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, TRUE);
    curl_setopt($ch, CURLOPT_PROXY, $ProxyServer);
    if (!empty($ProxyPort)) curl_setopt($ch, CURLOPT_PROXYPORT, $ProxyPort);
    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
  }
}

/**
 * @brief Usage
 * @param $argc
 * @param $argv
 **/
function Usage($argc, $argv)
{
  echo "$argv[0] -h -w {web host}\n";
  echo "         -h help\n";
  echo "         -w Web Host. Optional.\n";
  echo 'e.g.: ./regressiontest.php -w "http://bobg.fc.hp.com/trunk"\n';
}
