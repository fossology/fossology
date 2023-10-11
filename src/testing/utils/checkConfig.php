#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \brief Check if the test configuration file TestEnvironment exists, and
 * creates it.
 *
 * By default checkConfig checks if the test config file TestEnvironment exists
 * in the current directory and if not, creates it.  If the file exists the
 * program exits. Use the -o option to overwrite an existing file.
 * Use -s to specify an alternate source path.
 *
 * @param string (-s) $srcPath an alternate source path to test
 * @param boolean (-o) overwrite the existing file.
 *
 * @version "$Id $"
 *
 * Created on Jun 7, 2011 by Mark Donohoe
 */

$usage  = "$argv[0], -h -o -s <srcpath>
          -h: help
          -o: overwrite existing config file
          -s: path to alternate source\n";

$configFile = 'TestEnvironment.php';
$overWrite = FALSE;

$options = getopt('hos:');

if (array_key_exists('h', $options)) {
  print "$usage\n";
  exit(0);
}
if (array_key_exists('o', $options)) {
  $overWrite = TRUE;
}
if (array_key_exists('s', $options)) {
  $sourcePath = $options['s'];
}

// don't bother checking if file exists, just overwrite it.
if($overWrite)
{
  echo "overwritting file\n";
  $cconfig = callConfig();
  if(!$cconfig)
  {
    exit(1);   // fatal errors printed by callConfig
  }
  exit(0);
}
if(file_exists($configFile))
{
  echo "file exists, exiting\n";
  exit(0);
}
else
{
  echo "file does not exist, creating...\n";
  $exists = callConfig();
  if(!$exists)
  {
    exit(1);   // fatal errors printed by callConfig
  }
}
exit(0);

/**
 * \brief call configTestEnv.php, print errors on failure
 *
 * This function will print errors.  Even so, the return value should be
 * checked.
 *
 *
 * @return boolean
 */
function callConfig($sourcePath=NULL)
{
  
  // default path
  if(empty($sourcePath))
  {
    $sourcePath = '.';
  }
  // get fully qualified hostname
  // assume /repo
  $last = exec('hostname -f', $out, $rtn);
  if($rtn != 0)
  {
    echo "Fatal, could not get fully qalified hostname, cannot create config file.\n";
    echo "Error was\n";
    print_r($out) . "\n";
    return(FALSE);
  }

  $fossology = 'http://' . $last . '/repo/';
  $user = 'fossy';
  $pw = 'fossy';

  $cmd = $sourcePath . "/configTestEnv.php $fossology $user $pw 2>&1";
  $lastConfig = exec($cmd, $configOut, $rtn);
  //echo "last is:$lastConfig, out is:\n";print_r($configOut) . "\n";
  if($rtn != 0)
  {
    echo "Fatal, configTestEnv failed!, Error was:\n$lastConfig\n";
    print_r($configOut) . "\n";
    return(FALSE);
  }
  return(TRUE);
}
