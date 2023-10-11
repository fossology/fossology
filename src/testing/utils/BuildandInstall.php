#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * Nightly Build and Install of Top of Trunk
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
 * This script depends on jenkins environment variables
 *
 * @version "$Id$"
 *
 * Created on May 17, 2011
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
      $longMsg = file_get_contents($file);
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

// check if we are running under jenkins, and if so, use that path
if(array_key_exists('WORKSPACE', $_ENV))
{
  $JenkinsWkSpace = $_ENV['WORKSPACE'];
  $path = $JenkinsWkSpace;
  print "workspaces path is:$JenkinsWkSpace\n";
}

$tonight = new TestRun($path);

// Step 1 update sources

/* not sure the code below will be needed with jenkins...
 print "removing model.dat file so sources will update\n";
 $modelPath = $WORKSPACE . 'fossology/agents/copyright_analysis/model.dat';
 //$last = exec("rm $modelPath 2>&1", $output, $rtn);
 $last = exec("rm -f $modelPath ", $output, $rtn);

 // if the file doesn't exist, that's OK
 if((preg_match('/No such file or directory/',$last, $matches)) != 1)
 {
 if($rtn != 0)
 {
 $error = "Error, could not remove $modelPath, sources will not update, exiting\n";
 print $error;
 reportError($error,NULL);
 exit(1);
 }
 }
 print "Updating sources with svn update\n";
 if($tonight->svnUpdate() !== TRUE)
 {
 $error = "Error, could not svn Update the sources, aborting test\n";
 print $error;
 reportError($error,NULL);
 exit(1);
 }
 */
//TODO: remove all log files as sudo

// Step 2 make clean and make sources
print "Making sources\n";
if($tonight->makeSrcs() !== TRUE)
{
  $error = "There were Errors in the make of the sources examine make.out\n";
  print $error;
  reportError($error,'make.out');
  exit(1);
}
//try to stop the scheduler before the make install step.
print "Stopping Scheduler before install\n";
if($tonight->stopScheduler() !== TRUE)
{
  $error = "Could not stop fossology-scheduler, maybe it wasn't running?\n";
  print $error;
  reportError($error, NULL);
}

// Step 4 install fossology
print "Installing fossology\n";
if($tonight->makeInstall() !== TRUE)
{
  $error = "There were Errors in the Installation examine make-install.out\n";
  print $error;
  reportError($error, 'mi.out');
  exit(1);
}

// Step 5 run the post install process
/*
 for most updates you don't have to remake the db and license cache.  Need to
 add a -d for turning it off.
 */

print "Running fo-postinstall\n";
$iRes = $tonight->foPostinstall();
print "install results are:$iRes\n";

if($iRes !== TRUE)
{

  $error = "There were errors in the postinstall process check fop.out\n";
  print $error;
  print "calling reportError\n";
  reportError($error, 'fop.out');
  exit(1);
}

// Step 6 run the scheduler test to make sure everything is clean
print "Starting Scheduler Test\n";
if($tonight->schedulerTest() !== TRUE)
{
  $error = "Error! in scheduler test examine ST.out\n";
  print $error;
  reportError($error, 'ST.out');
  exit(1);
}

print "Starting Scheduler\n";
if($tonight->startScheduler() !== TRUE)
{
  $error = "Error! Could not start fossology-scheduler\n";
  print $error;
  reportError($error, NULL);
  exit(1);
}
