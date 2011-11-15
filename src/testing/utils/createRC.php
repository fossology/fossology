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
 * \brief determine where etc/fossology is and create a test rc file with the
 * location.
 *
 * @version "$Id$"
 * Created on Nov 15, 2011 by Mark Donohoe
 */

// first try the environment
// second look for a source install
// third look for a package install
// give up and error out.
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
$RC = fopen("$TESTROOT/fossologyTest.rc", 'w');
if($RC === FALSE)
{
  echo "FATAL! could not open $TESTROOT/fossologyTest.rc for writting\n";
  exit(1);
}
$many = fwrite($RC, $sysconf);
fclose($RC);
?>