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
 * \brief create an empty database with the supplied name.  Create user fossy
 * with password fossy.  If no datbase name is supplied the default name of fosstest will
 * be used.
 *
 * @param string -n $name optional name of data base to create
 *
 * @return boolean
 *
 * @version "$Id$"
 * Created on Sep 14, 2011 by Mark Donohoe
 */

$usage = __FILE__ . ": [-h] [-n name]";
$name = 'fosstest';           // default db name

$Options = getopt('hn:');
if (array_key_exists('h',$Options))
{
  print "$usage\n";
  exit(0);
}
if (array_key_exists('n',$Options))
{
  $name = $Options['n'];
  if(empty($name))
  {
    echo "Error! Valid data base name not supplied\n";
    exit(1);
  }
}

// figure out TESTROOT and export it to the environment
$dirList = explode('/', __DIR__);
// remove 1st entry which is empty
unset($dirList[0]);
$TESTROOT = NULL;
foreach($dirList as $dir)
{
  if($dir != 'testing')
  {
    $TESTROOT .= '/' .  $dir;
  }
  else if($dir == 'testing')
  {
    $TESTROOT .= '/' . $dir;
    break;
  }
}
//echo "after loop tr is:$TESTROOT\n";
$_ENV['TESTROOT'] = $TESTROOT;
// do I need to shell export?
if(chdir($TESTROOT . '/db') === FALSE)
{
  echo "FATAL! could no cd to $TESTROOT/db\n";
  exit(1);
}

$cmd = "./ftdbcreate.sh $name 2>&1";
$last = exec($cmd, $cmdOut, $cmdRtn);
if($cmdRtn != 0)
{
  echo "Error could not create Data Base $name\n";
  exit(1);
}
exit(0);
?>