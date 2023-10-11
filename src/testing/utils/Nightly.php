#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Nightly test runs of Top of Trunk
 *
 * @param -h
 * @param [ -p <path>] the path to the fossology sources
 *
 * @return
 *
 * \todo add parameters, e.g. -c for checkout?  -d for run fo-postinstall
 * but these parameters should have a + or - ?  as they are switches for
 * indicating don't do this.... as all actions are on be default.
 *
 * \todo save the fossology log file and start with a fresh one each night.
 *
 * @version "$Id: Nightly.php 3602 2010-10-23 01:20:35Z rrando $"
 *
 * Created on Dec 18, 2008
 */

require_once('TestRun.php');
require_once('mailTo.php');

global $mailTo;

$usage = "$argv[0] [-h] [-p <path>]\n" .
         "h: help, this message\n" .
         "p path: the path to the fossology sources to test\n";

$path = NULL;

$options = getopt('hp:');
if(array_key_exists('h', $options))
{
	echo $usage;
	exit(0);
}
if(array_key_exists('p', $options))
{
	$path = $options['p'];
}

/**
 * reportError
 * \brief report the test setup error in a mail message using the global
 * mailTo variable.
 *
 * @param string $error the error string to mail
 * @param string $file either the file path of a file or a string.  If
 * the parameter is a file path to a valid file, then the file will be
 * read and it's contents reported in the mail message.
 */
function reportError($error, $file=NULL)
{
	global $mailTo;

	if(is_file($file))
	{
		if(is_readable($file))
		{
			$longMsg = file_get_contnets($file);
		}
		else
		{
			$longMsg = "$file was not readable\n";
		}
	}
	else if(strlen($file) != 0)
	{
		if(is_string($file))
		{
			$longMsg = $file;
		}
		else
		{
			$longMsg = "Could not append a non-string to the message, " .
			           "reportError was passed an invalid 2nd parameter\n";
		}
	}

	$hdr = "There were errors in the nightly test setup." .
	       "The tests were not run due to one or more errors.\n\n";

	$msg = $hdr . $error . "\n" . $longMsg . "\n";

	// mailx will not take a string as the message body... save to a tmp
	// file and give it that.

	$tmpFile = tempnam('.', 'testError');
	$F = fopen($tmpFile, 'w') or die("Can not open tmp file $tmpFile\n");
	fwrite($F, $msg);
	fclose($F);
	$last = exec("mailx -s 'test Setup Failed' $mailTo < $tmpFile ",$tossme, $rptGen);
}

// Using the standard source path /home/fosstester/fossology

if(array_key_exists('WORKSPACE', $_ENV))
{
	$apath = $_ENV['WORKSPACE'];
	print "workspaces:\napath:$apath\n";
}

$tonight = new TestRun($path);

print "Running tests\n";
$testPath = "$tonight->srcPath" . "/tests";
print "testpath is:$testPath\n";
if(!chdir($testPath))
{
	$error = "Error can't cd to $testPath\n";
	print $error;
	reportError($error);
	exit(1);
}

print "Running Functional tests\n";
/*
 * This fails if run by fosstester as Db.conf is not world readable and the
 * script is not running a fossy... need to think about this...
 *
 */
$TestLast = exec('./testFOSSology.php -a -e', $results, $rtn);
print "after running tests the output is\n";
print_r($results) . "\n";


/*
 * At this point should have results, generate the results summary and email it.
 *
 * 10-29-2009: the results are generated in testFOSSology.php and mailed there
 * for now.... it should be done here.
 */


/*
 print "Stoping Scheduler\n";
 if($tonight->stopScheduler() !== TRUE)
 {
 print "Error! Could not stop fossology-scheduler\n";
 exit(1);
 }
 */
