#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2010 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * testFOSSology
 *
 * Run one or more FOSSology test suites
 *
 * @version "$Id: testFOSSology.php 3791 2011-01-22 04:25:19Z rrando $"
 *
 * \todo rewrite this POS, what was I thinking....?
 *
 * Created on Sept. 19, 2008
 */
/**
 * testFossology [-l path] {[-a] | [-b] | [-h] | [-s] | [-v -l]
 *
 * Run one or more FOSSology Test Suites
 * -a: run all tests suites.  -a overrides all other switches supplied.
 * -b: run the basic test suite. This runs the SiteTests and any
 * PageTests that don't depend on uploads
 * -s: run SiteTests only (this is a lightweight suite)
 * -p: run PageTests only. PageTests are the UI functional tests
 *
 * @param string either -a, -b, -s or -p
 *
 * @TODO: check for a initial / in the logfile path name? to guard
 * against the issue I ran into with it not writting to the correct
 * logfile.
 * @TODO: -c option for cleanup
 * @TODO switch to using fo-runTests for all tests.
 *
 */

require_once ('TestEnvironment.php');
require_once ('testClasses/check4jobs.php');
require_once ('mailTo.php');

$usage = NULL;
$usage.= "Usage: $argv[0] [-l path] {[-a] | [-b] | [-h] | [-s] | [-v -l]}\n";
$usage.= "-a: Run all FOSSology Test Suites\n" .
         "-b: Run the basic test suite. This runs the SiteTests and any Tests " .
         "that don't depend on uploads\n" .
         "-h: Display Usage\n" .
         "-l path: test results file path \n" .
         "-s: Run SiteTests only (this is a lightweight suite)\n" .
         "-v -l: Run the Verify Tests.  These tests require uploads to be uploaded first." .
         "    See the test documentation for details\n" .
         "    You must specify a log file when using -v, best to use the same log file" .
         "    that was used in a previous run for example -a -l foo, then -v -l foo when" .
         "    all the files have been uploaded with -a.";

global $logFile;
global $logFileName;
global $LF;
global $mailTo;

$SiteTests = '../ui/tests/SiteTests';
$BasicTests = '../ui/tests/BasicTests';
$UserTests = '../ui/tests/Users';
$EmailTests = '../ui/tests/EmailNotification';
$CopyRight = 'copyright';
$nomos = 'nomos';
$pkgAgent  = 'pkgagent';
$VerifyTests = '../ui/tests/VerifyTests';

/*
 * process parameters and run the appropriate test suite,
 * redirect  outputs to log file
 */
$errors = 0;
$date = date('Y-m-d');
$time = date('h:i:s-a');
$defaultLog = "/FossTestResults-$date-$time";
$myname = $argv[0];
$Home = getcwd();
$pid = getmypid();

$options = getopt('abehl:sv');
if (empty($options)) {
	print "$usage\n";
	exit(0);
}
if (array_key_exists('h', $options)) {
	print "$usage\n";
	exit(0);
}
if (array_key_exists('l', $options)) {
	$logFile = $options['l'];
	$logFileName = basename($logFile);
}
else {
	// Default Log file, use full path to it
	$cwd = getcwd();
	$logFile = $cwd . $defaultLog;
	//$logFileName = $defaultLog;
	$logFileName = basename($logFile);
}
/**
 * _runTestEnvSetup()
 * \brief upload the files needed to accomplished the functional tests
 * for the agents, nomos, copyright, and pkgagent.
 *
 */
function _runTestEnvSetup() {

	global $date;
	global $myname;
	global $Home;
	global $logFile;
	global $LF;

	$errors = 0;
	if (chdir($Home) === FALSE) {
		LogAndPrint($LF, "_runTestEnvSetup ERROR: can't cd to $Home\n");
	}
	LogAndPrint($LF, "\n");
	$UpLast = exec("./uploadTestData.php >> $logFile 2>&1", $dummy, $SUrtn);
	LogAndPrint($LF, "\n");
	$UpLast = exec("./fo-runTests.php  -l uploadCopyrightData.php -n 'Upload copyright data'>> $logFile 2>&1", $dummy, $Copyrtn);
	$AALast = exec("./fo-runTests.php -l AgentAddData.php -n 'Agent Add Uploads'>> $logFile 2>&1", $dummy, $AArtn);
	LogAndPrint($LF, "\n");
	// need to check the return on the setup and report accordingly.
	if ($SUrtn != 0) {
		LogAndPrint($LF, "ERROR when running uploadTestData.php\n");
		foreach($dummy as $ErrorLine) {
			print "$ErrorLine\n";
		}
		$errors++;
	}
	if ($Copyrtn != 0) {
		LogAndPrint($LF, "ERROR when running uploadCopyrightData.php\n");
		foreach($dummy as $ErrorLine) {
			print "$ErrorLine\n";
		}
		$errors++;
	}
	if ($AArtn != 0) {
		LogAndPrint($LF, "ERROR when running AgentAddData.php\n");
		foreach($dummy as $ErrorLine) {
			print "$ErrorLine\n";
		}
		$errors++;
	}
	if ($errors != 0) {
		print "Warning! There were errors in the test setup, one or more test may fail as a result\n";
	}
} //_runTestEnvSetup

function getSvnVer() {
	return (`svnversion`);
}

function LogAndPrint($FileHandle, $message) {
	if (empty($message)) {
		return (FALSE);
	}
	if (empty($FileHandle)) {
		return (FALSE);
	}
	if (-1 == fwrite($FileHandle, $message)) {
		print $message; // if we don't do this nothing will print
		flush();
		return (FALSE);
	}
	print $message;
	flush();
	return (TRUE);
}

$Svn = getSvnVer();

/************* ALL Tests **********************************************/
if (array_key_exists("a", $options)) {
	$LF = fopen($logFile, 'w') or die("can't open $logFile $phperrormsg\n");
	print "Using log file:$logFile\n";
	if (chdir($Home) === FALSE) {
		LogAndPrint($LF, "All Tests ERROR: can't cd to $Home\n");
	}
	LogAndPrint($LF, "Running All Tests on: $date at $time using subversion version: $Svn\n");

	$Cleanup = "This program does not remove the testing folders in case" .
						 " there was a failure and it needs to be looked at.  Run " .
						 "the script/test runTestCleanup.php to clean things up.\n";

	print wordwrap($Cleanup);

	/*
	 * Create the test users first or nothing will work should this be somewhere else like
	 * in install?
	 */
	$cmd = "./fo-runTests.php createUIUsers.php -n 'Create UI Users Test' >> $logFile 2>&1";
	$UIusers = exec($cmd, $dummy, $UsrRtn);
	if ($UsrRtn != 0) {
		LogAndPrint($LF, "ERROR when running createUIUsers.php: return code:$UsrRtn\n");
		LogAndPrint($LF, "last line returned is:$UIusers\n");
		foreach($dummy as $ErrorLine) {
			LogAndPrint($LF,"$ErrorLine\n");
		}
		LogAndPrint($LF, "The Email Notification tests mail fail as a result\n");
	}
	$ctdLast = exec('./checkTestData.php', $ctdOut, $ctdRtn);
	if($ctdRtn != 0)
	{
	  LogAndPrint($LF, "ERROR when running check4TestData, see previous errors\n");
	}

	if (chdir($SiteTests) === FALSE) {
		LogandPrint($LF, "ALL Tests ERROR: can't cd to $SiteTests\n");
	}
	//print "testFOSS: path is:$_ENV[PATH]\n";

	$SiteLast = exec("./runSiteTests.php >> $logFile 2>&1", $dummy, $Srtn);
	LogAndPrint($LF, "\n");
	if (chdir('../BasicTests') === FALSE) {
		LogAndPrint($LF, "ALL Tests ERROR: can't cd to $BasicTests\n");
	}
	$BasicLast = exec("./runBasicTests.php >> $logFile 2>&1", $dummy, $Brtn);
	LogAndPrint($LF, "\n");

	/*
	 * run user tests, create UI users then run Email Notification tests
	 */
	if (chdir('../Users') === FALSE) {
		LogAndPrint($LF, "ALL Tests ERROR: can't cd to $UserTests\n");
	}
  $testList = "addUserTest.php dupUserTest.php noEmailUserTest.php userEditAnyTest.php";
  $uCmnd = "/usr/local/bin/fo-runTests -l $testList -n 'User Tests' >> $logFile 2>&1";
	$UsersLast = exec($uCmnd, $dummy, $Urtn);
	LogAndPrint($LF, "\n");

	if (chdir($Home) === FALSE) {
		$cUInoHome = "All Tests ERROR: can't cd to $Home\n";
		LogAndPrint($LF, $cUInoHome);
	}

	if (chdir($EmailTests) === FALSE) {
		LogAndPrint($LF, "ALL Tests ERROR: can't cd to $EmailTests\n");
	}

	$EmailLast = exec("fo-runTests -l \"`ls`\" -n 'Email Tests' >> $logFile 2>&1", $dummy, $ENrtn);
	LogAndPrint($LF, "\n");

	if (chdir($Home) === FALSE) {
		$cUInoHome = "All Tests ERROR: can't cd to $Home\n";
		LogAndPrint($LF, $cUInoHome);
	}

	if (chdir($pkgAgent) === FALSE) {
		LogAndPrint($LF, "ALL Tests ERROR: can't cd to $pkgAgent\n");
	}
	$PkgLast = exec("fo-runTests -l \"`ls cli*`\" -n 'Package Agent Tests'" .
										" >> $logFile 2>&1", $dummy, $Pkgrtn);
	LogAndPrint($LF, "\n");

	/*
	 * The verify tests require that uploads be done first.
	 */
	_runTestEnvSetup();

	// wait for uploads to finish
	if (chdir($Home) === FALSE) {
		$UInoHome = "All Tests ERROR: can't cd to $Home\n";
		LogAndPrint($LF, $UInoHome);
	}
	print "Waiting for jobs to finish...\n";
	$last = exec('./wait4jobs.php', $tossme, $jobsDone);
	foreach($tossme as $line){
		print "$line\n";
	}
	print "testFOSSology: jobsDone is:$jobsDone\n";
	if ($jobsDone != 0) {
		print "ERROR! jobs are not finished after two hours, not running" .
    "verify tests, please investigate and run verify tests by hand\n";
		print "Monitor the job Q and when the setup jobs are done, run:\n";
		print "$myname -v -l $logFile\n";
		exit(1);
	}
	if ($jobsDone == 0) {
		if (chdir($Home) === FALSE) {
			$cUInoHome = "All Tests ERROR: can't cd to $Home\n";
			LogAndPrint($LF, $cUInoHome);
		}
		if (chdir($nomos) === FALSE) {
			LogAndPrint($LF, "ALL Tests ERROR: can't cd to $nomos\n");
		}
		// Nomos functional tests
		$nomosTests = array('ckZendTest.php', 'verifyRedHatTest.php');
		foreach($nomosTests as $test)
		{
			$last = exec("fo-runTests -l $test -n 'Nomos Tests' >> $logFile 2>&1", $dummy, $rtn);
			LogAndPrint($LF, "\n");
		}

		LogAndPrint($LF, "Starting cli1Test\n" );
		$last = exec('phpunit ./cli1Test.php', $punitOut, $puRtn);
    if($puRtn != 0)
    {
      LogAndPrint($LF, "cli1Test Failed\n");
      LogAndPrint($LF, $punitOut);
      LogAndPrint($LF, "\n");
    }
    else
    {
      LogAndPrint($LF, "cli1Test Passed\n");
      LogAndPrint($LF, "$last\n");
    }

		if (chdir($Home) === FALSE) {
			$cUInoHome = "All Tests ERROR: can't cd to $Home\n";
			LogAndPrint($LF, $cUInoHome);
		}
		// Copyright tests
		if (chdir($CopyRight) === FALSE) {
			LogAndPrint($LF, "ALL Tests ERROR: can't cd to $CopyRight\n");
		}
		$CopyLast = exec("fo-runTests -l \"`ls verify*`\" -n 'CopyRight Tests' >> $logFile 2>&1", $dummy, $CRrtn);
		LogAndPrint($LF, "\n");

    // phpunit tests
    $last = exec('phpunit cliParamsTest.php', $punitOut, $puRtn);
    if($puRtn != 0)
    {
      LogAndPrint($LF, "cli1Test Failed\n");
      LogAndPrint($LF, $punitOut);
      LogAndPrint($LF, "\n");
    }
    else
    {
      LogAndPrint($LF, "cli1Test Passed\n");
      LogAndPrint($LF, "$last\n");
    }

		fclose($LF);
		verifyUploads($logFile);
		if (!is_null($rtn = saveResults())) {
			print "ERROR! could not save the test results, please save by hand\n";
			print "saveResults returned the following error:\n$rtn\n";
			exit(1);
		}
		$resultsHome = "/home/fosstester/public_html/TestResults/Data/Latest/";
		$reportHome = "$resultsHome" . "$logFileName";

		if(array_key_exists('e', $options)) {
			$last = exec("./textReport.php -f $reportHome |
    mailx -s \"test results\" $mailTo ",$tossme, $rptGen);
		}
		$last = system("./textReport.php -f $reportHome", $rtn);
		if($last === FALSE) {
			print "Error! Counld not generate text summary report\n";
			exit(1);
		}
	}
	exit(0);
}
/**************** Basic Tests (includes Site) *************************/
if (array_key_exists("b", $options)) {
	$LF = fopen($logFile, 'w') or die("can't open $logFile $phperrormsg\n");
	print "Using log file:$logFile\n";
	if (chdir($Home) === FALSE) {
		$BnoHome = "Basic Tests ERROR: can't cd to $Home\n";
		LogAndPrint($LF, $BnoHome);
	}
	$startB = "Running Basic/SiteTests on: $date at $time\n";
	LogAndPrint($LF, $startB);
	if (chdir($SiteTests) === FALSE) {
		$noBS = "Basic/Site Tests ERROR: can't cd to $SiteTests\n";
		LogAndPrint($LF, $noBS);
	}
	print "\n";
	$SiteLast = exec("./runSiteTests.php >> $logFile 2>&1", $dummy, $Srtn);
	if (chdir('../BasicTests') === FALSE) {
		$noBT = "Basic Tests ERROR: can't cd to $BasicTests\n";
		LogAndPrint($LF, $noBT);
	}
	print "\n";
	$BasicLast = exec("./runBasicTests.php >> $logFile 2>&1", $dummy, $Srtn);
	fclose($LF);
	exit(0);
}
/***************** SiteTest Only **************************************/
if (array_key_exists("s", $options)) {
	$LF = fopen($logFile, 'w') or die("can't open $logFile $phperrormsg\n");
	print "Using log file:$logFile\n";
	$Sstart = "Running SiteTests on: $date at $time\n";
	LogAndPrint($LF, $Sstart);
	if (chdir($SiteTests) === FALSE) {
		$noST = "Site Tests ERROR: can't cd to $SiteTests\n";
		LogAndPrint($LF, $noST);
	}
	$SiteLast = exec("./runSiteTests.php >> $logFile 2>&1", $dummy, $Srtn);
	fclose($LF);
	exit(0);
}
/******************** Verify ******************************************/
if (array_key_exists("v", $options)) {
	if (array_key_exists("l", $options)) {
		$logFile = $options['l'];
		/*
		 * check if it starts with a slash, if not assume it's relative and make
		 * a complete path of it.  If you don't the results end up in the verifyTests
		 * directory.
		 */
		$position = strpos($logFile,'/');
		if($position === FALSE) {
			$logFile = getcwd() . "/$logFile";
		}
		$logFileName = basename($logFile);
	} else {
		print "Error, must supply a path to a log file with -v option\n";
		print $usage;
		exit(1);
	}
	print "calling verifyUploads with:$logFile\n";
	if (!verifyUploads($logFile)) {
		print "NOTE: One or more verify upload tests had errors, please investigate\n";
		echo "by running ui/tests/VerifyTests/runVerifyTests.php\n";
		exit(1);
	}
	if (!is_null($rtn = saveResults())) {
		print "ERROR! could not save the test results, please save by hand\n";
		exit(1);
	}
	exit(0);
}
function saveResults() {

	global $Home;
	global $logFileName;
	global $LF;
	global $logFile;

	$resultsHome = "/home/fosstester/public_html/TestResults/Data/Latest/";
	if (chdir($Home) === FALSE) {
		$nohome = "Save Data ERROR: can't cd to $Home\n";
		LogAndPrint($LF, $nohome);
		return ($nohome);
	}
	//print "saveResults: logFileName is:$logFileName\n";
	//print "saveResults: resultsHome is:$resultsHome\n";
	$reportHome = "$resultsHome" . "$logFileName";
	if (!rename($logFile, $reportHome)) {
		$E = "Error, could not move\n$logFile\nto\n$reportHome\n";
		$E.= "Please move it by hand so the reports will be current\n";
		return ($E);
	}
	return (NULL);
}
function verifyUploads($logfile) {
	global $Home;
	global $VerifyTests;
	global $date;
	global $time;
	if (empty($logfile)) {
		return (FALSE);
	}
	$VLF = fopen($logfile, 'a') or die("Can't open $logfile, $phperrormsg");
	$Vstart = "\nRunning Verify Tests on: $date at $time\n";
	LogAndPrint($VLF, $Vstart);
	if (chdir($Home) === FALSE) {
		$noVhome = "Verify Tests ERROR: can't cd to $Home\n";
		LogAndPrint($VLF, $noVhome);
	}
	if (chdir($VerifyTests) === FALSE) {
		$noVT = "Verify Tests ERROR: can't cd to $VerifyTests\n";
		LogAndPrint($VLF, $noVT);
	}
	fclose($VLF);
	echo "Running runVerifyTests\n";
	$VerifyLast = exec("./runVerifyTests.php >> $logfile 2>&1", $dummy, $Vrtn);
	if($Vrtn == 0) {
		return(TRUE);
	}
	else {
		return(FALSE);
	}
}
