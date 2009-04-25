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
 * @version "$Id$"
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
$OK = array();
print "installing fo-runTests into /usr/local/bin\n";
$wd = getcwd();
$cmd = "ln -s $wd/fo-runTests.php /usr/local/bin/fo-runtests 2>&1";
$last = exec($cmd, $tossme, $rtn);
if($rtn != 0) {
  $OK = preg_grep('/File exists/', $tossme);
  if(empty($OK)) {
    print "Error, could not create sym link in /usr/local/bin for fo-runTests\n";
    exit(1);
  }
}

/*
 * Create the system users,
 */

print "Creating fosstester and noemail users\n";
if(!is_executable("./CreateTestUser.sh")) {
  if(!chmod("./CreateTestUser.sh",0755)) {
    print "FATAL, could not make ./CreateTestUser.sh executable\n";
    exit(1);
  }
}
$last = exec("./CreateTestUser.sh",$tossme, $rtn);
if($rtn != 0) {
  print "CreateTestUser.sh Failed, Investigate, run by hand\n";
}

/* load data into fosstester account */
print "loading test data into the fosstester home directory\n";
$last = exec("./installTestData.sh",$tossme, $rtn);
/*
print "output from installTestData is:\n";
foreach($tossme as $line){
  print "$line\n";
}
*/

$Tconfig = getcwd();
print "adjusting servers file in .subversion so checkouts work\n";
if(chdir('/home/fosstester/.subversion') === TRUE) {
  if(!copy('servers.hp', 'servers')) {
    print "Warning! could not adjust servers file, may not be able to check out sources\n";
  }
}

if(chdir($Tconfig) === FALSE){
  print "Warning! cannot cd to $Tconfig, the next steps may fail\n";
}
/*
 * Create the UI users for the tests
 */
print "Creating UI test users fosstester and noemail\n";
// fix this... should get the host name and domain and use that....
$last = exec("./configTestEnv.php 'http://localhost/repo/' fossy fossy",$tossme, $rtn);
if($rtn != 0) {
  print "./configTestEnv.php Failed for fossy, Investigate\n";
}
$last = exec("./fo-runTests.php -l 'createUIUsers.php'",$tossme, $rtn);
if($rtn != 0) {
  print "./createUIUsers Failed!, Investigate\n";
}
$last = exec("./configTestEnv.php 'http://localhost/repo/' fosstester fosstester",$tossme, $rtn);
if($rtn != 0) {
  print "./configTestEnv.php Failed for fosstester, Investigate\n";
}
?>