#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \brief determine where etc/fossology is and create a test rc file with the
 * location.  Export SYSCONDIR path to the environment as well.
 *
 * @version "$Id$"
 * Created on Nov 15, 2011 by Mark Donohoe
 */

// first try the environment
// second look for a source install
// third look for a package install
// give up and error out.

// @todo who uses the rc file?

$sysconf = NULL;

if(!defined('TESTROOT'))
{
  $path = __DIR__;
  $plenth = strlen($path);
  // remove /utils from the end.
  $TESTROOT = substr($path, 0, $plenth-6);
  $_ENV['TESTROOT'] = $TESTROOT;
  putenv("TESTROOT=$TESTROOT");
  define('TESTROOT',$TESTROOT);
}

$sysconf = getenv('SYSCONFDIR');
if($sysconf === FALSE)
{
  if(file_exists('/usr/local/etc/fossology'))
  {
    $sysconf = '/usr/local/etc/fossology';
  }
  else if(file_exists('/etc/fossology'))
  {
    $sysconf = '/etc/fossology';
  }
}
if($sysconf === FALSE || $sysconf == NULL)
{
  echo "FATAL! cannot determine where the fossology sysconfigdir is located\n";
  exit(1);
}
$RC = fopen("fossology.rc", 'w');
if($RC === FALSE)
{
  echo "FATAL! could not open fossology.rc for writting\n";
  exit(1);
}
$many = fwrite($RC, $sysconf);
fclose($RC);

// put in globals and export to environment.
echo "DBCRC: sysconf is:$sysconf\n";
echo "DBCRC: exporting sysconf to env and globals.\n";
$GLOBALS['SYSCONFDIR'] = $sysconf;
putenv("SYSCONFDIR={$GLOBALS['SYSCONFDIR']}");
$_ENV['SYSCONFDIR'] = $GLOBALS['SYSCONFDIR'];
