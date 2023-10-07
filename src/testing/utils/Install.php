#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * Install
 *
 * Install the fossology test suite support items
 *
 * @version "$Id: Install.php 2632 2009-11-13 01:15:10Z rrando $"
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

/**
 * installST
 *
 * install simpletest into /usr/local
 *
 * @return boolean
 */
function installST() {
	$here = getcwd();
	if (is_readable('/etc/fossology/Proxy.conf')) {
		print "Using proxy found in file /etc/fossology/Proxy.conf\n";
		$cmd = ". /etc/fossology/Proxy.conf;" .
    "wget -nv -t 1 'http://downloads.sourceforge.net/simpletest/simpletest_1.0.1.tar.gz'";
	}
	else if (is_readable('/usr/local/etc/fossology/Proxy.conf')) {
		print "Using proxy found in file /usr/local/etc/fossology/Proxy.conf\n";
		$cmd = ". /usr/local/etc/fossology/Proxy.conf;" .
    "wget -nv -t 1 'http://downloads.sourceforge.net/simpletest/simpletest_1.0.1.tar.gz'";
	}
	else {
		print "No proxy used when attempting to download simpletest\n";
		$cmd = "wget -nv -t 1 'http://downloads.sourceforge.net/simpletest/simpletest_1.0.1.tar.gz'";
	}
	if(chdir('/usr/local/')) {
		$wLast = exec($cmd, $wgetOut, $rtn);
		if($rtn == 0) {  // download worked
			$tar = 'tar -xf simpletest_1.0.1.tar.gz';
			$tLast = exec($tar, $tout, $rtn);
			if(is_readable('/usr/local/simpletest')) {
				/* clean up, try to remove the downloaded archive, */
				$rl = exec('rm simpletest_1.0.1.tar.gz', $toss, $notchecked);
				chdir($here);
				return(TRUE);  // un tar worked, installed.
			}
			else {
				print "ERROR! failed to un-tar simpletest into /usr/local\n";
				print "tar output was:$tLast\n";print_r($tout) . "\n";
				print "Investigate and install simpletest into /usr/local then rerun this script\n";
				return(FALSE);
			}
		}
		else {
			print "ERROR! problem with downloading simpletest with wget, need a proxy?\n";
			print "wget output was:$wLast\n";print_r($wgetOut) . "\n";
			print "Investigate and install simpletest into /usr/local then rerun this script\n";
			return(FALSE);
		}
	}
	else {
		print "ERROR! cannot cd to /usr/local\n";
		print "Investigate and install simpletest into /usr/local then rerun this script\n";
		return(FALSE);
	}
	chdir('$here'); // should never get here, but cd back just in case....
	return(FALSE);
}

/* Create sym link to fo-runTests, the code below doesn't work well.  Just remove
 * what is found and replace....
 */
$OK = array();
print "installing fo-runTests into /usr/local/bin\n";
$wd = getcwd();
$cmd = "ln -s $wd/fo-runTests.php /usr/local/bin/fo-runTests 2>&1";
$last = exec($cmd, $tossme, $rtn);
if($rtn != 0) {
	$OK = preg_grep('/File exists/', $tossme);
	if(empty($OK)) {
		print "Error, could not create sym link in /usr/local/bin for fo-runTests\n";
		exit(1);
	}
	else {   // link exists, remove and recreate
		$rm = 'rm /usr/local/bin/fo-runTests';
		$last = exec($rm, $tossme, $rtn);
		if($rtn != 0) {
			print "Error, could not remove /usr/local/bin/fo-runTests\n";
			print "Remove by hand and remake the symbolic link to the appropriate test source\n";
			exit(1);
		}
		$last = exec($cmd, $tossme, $rtn);
		if($rtn != 0) {
			print "Error, could not create sym link in /usr/local/bin for fo-runTests\n";
			print "Investigate and remake the symbolic link to the appropriate test source\n";
			exit(1);
		}
	}
}

/* Make sure simpletest is installed, if not, install it. */
print "Check to see if simpletest is installed in /usr/local\n";

if(!is_readable('/usr/local/simpletest')) {
	print "Attempting to download and install simpletest into /usr/local\n";
	$ok = installST();
	if(!$ok) {
		print "FATAL ERROR!, install simpletest into /usr/local, then rerun this script\n";
		exit(1);
	}
}

/*
 * Create the db user and system users,
 */
if(!is_executable("./makeDbUser")) {
	if(!chmod("./makeDbUser",0755)) {
		print "FATAL, could not make ./makeDbUser executable\n";
		exit(1);
	}
}
$last = exec("./makeDbUser",$tossme, $rtn);
if($rtn != 0) {
	print "makeDbUser Failed, Investigate, run by hand\n";
}

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

foreach($tossme as $line){
	print "$line\n";
}

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
/* Remove the symlink in /usr/local/bin for fo-runTests it will get reestablished
 * when fosstester user configures.
 */
echo "Removing fo-runtests link in /usr/local/bin/\n";
$last = exec('sudo rm /usr/local/bin/fo-runTests', $tossme, $rtn);
echo "Creating fo-runtests link in /usr/local/bin/\n";
$last = exec("ln -s fo-runtests.php /usr/local/bin/fo-runtests",$tossme, $rtn);
if($rtn != 0) {
  print "FATAL! Could create fo-runtests link, Investigate and create by hand\n";
}
