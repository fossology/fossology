#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
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
