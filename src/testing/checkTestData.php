#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \brief Check if the test data files exist, if not downloads and installs them.
 *
 * Large test data files are kept outside of source control.  The data needs to
 * be installed in the sources before tests can be run.  The data is kept on
 * fossology.org in /var/www/fossology.og/testing/testFiles/
 *
 * @version "$Id$"
 *
 * Created on Jun 8, 2011 by Mark Donohoe
 */

$home = getcwd();
echo "DB: at the start.... home is:$home\n";
$dirs = explode('/',$home);
$size = count($dirs);
// are we being run by jenkins? if we are not in fossology/tests, cd there
if($dirs[$size-1] == 'workspace' )
{
  if(chdir('fossology/src/testing') === FALSE)
  {
    echo "FATAL! Cannot cd to fossology/src/testing from" . getcwd() . "\n";
    exit(1);
  }
  $home = getcwd();  // home should now be ...workspace/fossology/src/testing
}
echo "DB: home is:$home\n";

$redHatPath = 'nomos/testdata';
$unpackTestFile = '../../ununpack/agent_tests/test-data/testdata4unpack/argmatch.c.gz';
$unpackTests = '../../ununpack/agent_tests';
$redHatDataFile = 'RedHat.tar.gz';
$unpackDataFile = 'unpack-test-data.tar.bz2';
$wgetOptions = ' -a wget.log --tries=3 ';
$proxy = 'export http_proxy=lart.usa.hp.com:3128;';
$Url = 'http://fossology.org/testing/testFiles/';

$errors = 0;
// check/install RedHat.tar.gz

/*
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
else

if(chdir($home) === FALSE)
{
  echo "FATAL! could not cd to $home\n";
  exit(1);
}
*/

// check/install ununpack data
echo "downloading unpack data.....\n";
if(!file_exists($unpackTestFile))
{
  echo "$unpackTestFile DOES NOT EXIST!, need to download data files...\n";
  if(chdir($unpackTests) === FALSE)
  {
    echo "FATAL! cannot cd to $unpackTests\n";
    exit(1);
  }
  $cmd = $proxy . "wget" . $wgetOptions . $Url . '/' . $unpackDataFile;
  $unpkLast = exec($cmd, $unpkOut, $unpkRtn);
  if($unpkRtn != 0)
  {
    echo "ERROR! Download of $Url$unpackDataFile failed:$unpkRtn\n";
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
