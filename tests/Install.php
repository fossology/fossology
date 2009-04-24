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
 * Install
 *
 * Install the fossology test suite support items
 *
 * @version "$Id:  $"
 *
 * Created on April 23, 2009
 */

/**
 * @TODO add parameters: -u for user -p for password, then add in running the
 * test env script.
 * @TODO add in verbose option and code to support it.
 */

/* Check for Super User */
$euid = posix_getuid();
if($euid != 0) {
  print "Error, this script must be run as root\n";
  exit(1);
}

/* Create sym link to fo-runTests */
print "installing fo-runTests into /usr/local/bin\n";
$wd = getcwd();
$last = exec("ln -s /usr/local/bin/fo-runtests $wd/fo-runTests.php",$tossme, $rtn);
print "return code from symlink is:$rtn\n";
if($rtn != 0) {
  print "Error, could not create sym link in /usr/local/bin for fo-runTests\n";
  print "the link command returned:\n";
  print_r($tossme) . "\n";
  exit(1);
}

/*
 * Create the system users,
 */

print "Creating fosstester and noemail users\n";
$last = exec("./CreateTestUser.sh",$tossme, $rtn);
if($rtn != 0) {
  print "Failuer? got $rtn from CreateTestUser, Investigate\n";
}

/* load data into fosstester account */
print "loading test data into the fosstester home directory\n";
$last = exec("./installTestData.sh",$tossme, $rtn);
if($rtn != 0) {
  print "Failuer? got $rtn from CreateTestUser, Investigate\n";
}

/*
 * Create the UI users for the tests
 */
print "Creating UI test users fosstester and noemail\n";
// fix this... should get the host name and domain and use that....
$last = exec("./configTestEnv.php 'http://localhost/repo/' fossy fossy",$tossme, $rtn);
if($rtn != 0) {
  print "Failuer? got $rtn from configTestEnv, Investigate\n";
}
$last = exec("./fo-runTests.php -l 'createUIUsers.php'",$tossme, $rtn);
if($rtn != 0) {
  print "Failuer? got $rtn from createUIUsers, Investigate\n";
}
$last = exec("./configTestEnv.php 'http://localhost/repo/' fosstester fosstester",$tossme, $rtn);
if($rtn != 0) {
  print "Failuer? got $rtn from configTestEnv, Investigate\n";
}
?>