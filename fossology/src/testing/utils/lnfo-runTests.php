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
 * lnfo-runTests
 *
 * Install a symlink in /usr/local/bin to the test run script
 *
 * @version "$Id: lnfo-runTests.php 3086 2010-04-21 01:18:44Z rrando $"
 *
 * Created on April 24, 2009
 */

/* Check for Super User */
$euid = posix_getuid();
if($euid != 0) {
	print "Error, this script must be run as root\n";
	exit(1);
}

/* Create sym link to fo-runTests */
$OK = array();
print "installing fo-runTests into /usr/local/bin\n";
$wd = getcwd();
$rmCmd = "rm /usr/local/bin/fo-runTests 2>&1";
$last = exec($rmCmd, $tossme, $rtn);
if($rtn != 0) {
	$OK = preg_grep('/No such file/', $tossme);
  if(empty($OK)) {
  	print "Error, could not remove /usr/local/bin/fo-runTests, remove by hand\n";
  	exit(1);
  }
}
$cmd = "ln -s $wd/fo-runTests.php /usr/local/bin/fo-runTests 2>&1";
$last = exec($cmd, $tossme, $rtn);
if($rtn != 0) {
	print "Error, could not create sym link in /usr/local/bin for fo-runTests\n";
	exit(1);
}
exit(0);
?>