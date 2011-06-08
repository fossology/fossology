#!/usr/bin/php
<?php
/*
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
 * \brief Check if the test data files exist, if not downloads and installs them.
 *
 * Large test data files are kept outside of source control.  The data needs to
 * be installed in the sources before tests can be run.  The data is kept on
 * fossology.org in /var/www/fossology.og/testing/testFiles/
 *
 * @version "$Id $"
 *
 * Created on Jun 8, 2011 by Mark Donohoe
 */

// Assumes running from fossology/tests

/*
 * 1. check the paths for the data
 * if exists, next data set, if not download it
 * 2. wget the data
 * 3. unpack if needed.
 */

$home = getcwd();
$redHatPath = 'nomos/testdata';
$unpackPath = 'agents/ununpack/tests/testdata/testdata4unpack';
$unpackTests = '../agents/ununpack/tests';
$redHatDataFile = 'RedHat.tar.gz';
$unpackDataFile = 'unpack-test-data.tar.bz2';
$wgetOptions = ' -a wget.log --tries=3 ';
$proxy = 'export http_proxy=lart.usa.hp.com:3128;';
$Url = 'http://fossology.org/testing/testFiles/';

$errors = 0;
// check/install RedHat.tar.gz

if(!file_exists($redHatPath . "/" . $redHatDataFile))
{
  if(chdir($redHatPath) === FALSE)
  {
    echo "ERROR! could not cd to $redHatPath, cannot download $redHatDataFile\n";
    $errors++;
  }
  $cmd = $proxy . "wget" . $wgetOptions . $Url . $redHatDataFile;
  $last = exec($cmd, $wgetOut, $wgetRtn);
  if($wgetRtn != 0)
  {
    echo "ERROR! Download of $Url$redHatDataFile failed\n";
    echo "Errors were:\n$last\n";print_r($wgetOut) . "\n";
    $errors++;
  }
}

if(chdir($home) === FALSE)
{
  echo "FATAL! could not cd to $home\n";
  exit(1);
}

// check/install ununpack data
if(!file_exists($unpackPath))
{
  if(chdir($unpackTests) === FALSE)
  {
    echo "FATAL! cannot cd to $unpackTests\n";
    exit(1);
  }
  $cmd = $proxy . "wget" . $wgetOptions . $Url . '/' . $unpackDataFile;
  $unpkLast = exec($cmd, $unpkOut, $unpkRtn);
  if($unpkRtn != 0)
  {
    echo "ERROR! Download of $Url$unpackDataFile failed\n";
    echo "Errors were:\n";print_r($unpkOut) . "\n";
    $errors++;
  }
  // unpack the tar file.
  $cmd = "tar -xf $unpackDataFile";
  $tarLast = exec($cmd, $tarOut, $tarRtn);
  if($tarRtn != 0)
  {
    echo "ERROR! un tar of $unpackDataFile failed\n";
    echo "Errors were:\n$tarLast\n";print_r($tarOut) . "\n";
    $errors++;
  }
}
if($errors)
{
  exit(1);
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
?>