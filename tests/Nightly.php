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
 * Nightly test runs of Top of Trunk
 *
 * @param
 *
 * @return
 *
 * @version "$Id: $"
 *
 * Created on Dec 18, 2008
 */

require_once('TestRun.php');

// Using the standard source path /home/fosstester/Src/fossology
$tonight = new TestRun();

// Step 1 update sources
print "Updating sources with svn update\n";
if($tonight->svnUpdate() !== TRUE)
{
  print "Error, could not svn Update the sources, aborting test\n";
  exit(1);
}

// Step 2 make clean and make sources
print "Making sources\n";
if($tonight->makeSrcs() !== TRUE)
{
  print "There were Errors in the make of the sources examine make.out\n";
  exit(1);
}
//try to stop the scheduler before the make install step.
if($tonight->stopScheduler() !== TRUE)
{
  print "Could not stop fossology-scheduler, maybe it wasn't running?\n";
}

// Step 4 install fossology
print "Installing fossology\n";
if($tonight->makeInstall() !== TRUE)
{
  print "There were Errors in the Installation examine make-install.out\n";
  exit(1);
}

// Step 5 run the post install process
print "Running fo-postinstall\n";
if($tonight->foPostinstall() !== TRUE)
{
  print "There were errors in the postinstall process check fop.out\n";
  exit(1);
}

// Step 6 run the scheduler test to make sure everything is clean
print "Starting Scheduler Test\n";
if($tonight->schedulerTest() !== TRUE)
{
  print "Error! in scheduler test examine ST.out\n";
  exit(1);
}

print "Starting Scheduler\n";
if($tonight->startScheduler() !== TRUE)
{
  print "Error! Could not start fossology-scheduler\n";
  exit(1);
}

print "Running tests\n";
$testPath = "$tonight->srcPath" . "/tests";
print "testpath is:$testPath\n";
if(!chdir($testPath))
     {
       print "Error can't cd to $testPath\n";
       exit(1);
     }

$TestLast = exec('./testFOSSology.php -a', $results, $rtn);
print "after running tests the output is\n";
print_r($results) . "\n";

/*
 * ok, works for running -a, now need to figure out how to tell if all
 * my jobs are done? then run the verifier part...
 */

/*
print "Stoping Scheduler\n";
if($tonight->stopScheduler() !== TRUE)
{
  print "Error! Could not stop fossology-scheduler\n";
  exit(1);
}
*/

?>
