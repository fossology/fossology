#!/usr/bin/php
<?php
/*
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
 * runUnitTests
 * \brief run 1 or more unit tests, copy xml and raw results and copy 
 * dtd's and xsl's to hudson area if running under hudson or to 
 * ~fosstester/public_html/unitTests.
 *
 * @version "$Id $"
 *
 * Created on Oct 18, 2010
 */

require_once('../TestEnvironment.php');

global $TR;
global $testList;

$TR = TESTROOT;
$publish = '/home/fosstester/public_html/unitTests';
$testList = array('copyright');

print "testroot is:$TR\n";

function cd2TestRoot()
{
	global $TR;
	
	if(!chdir($TR))
	{
		echo "FATAL! cannot cd to $TR\n";
		exit(1);
	}
	return;
} // cd2TestRoot

/**
 * copyResults
 * \brief copy the unit test xml result files, the raw .txt file and 
 * the support files needed to display the xml to the fosstester 
 * public_html area
 * 
 * @param string $publish, the path to copy the results to
 */
function copyResults($publish)
{

	global $TR;
	global $testList;
	
	$cunitDTD = array('/usr/share/CUnit/CUnit-List.dtd',
                   '/usr/share/CUnit/CUnit-Run.dtd',
                   '/usr/share/CUnit/Memory-Dump.dtd');
	$cunitXSL = array('/usr/share/CUnit/CUnit-List.xsl',
                   '/usr/share/CUnit/CUnit-Run.xsl',
                   '/usr/share/CUnit/Memory-Dump.xsl');

	foreach($cunitDTD as $file)
	{
		$baseName = pathinfo($file,PATHINFO_BASENAME);
		if(!copy($file, $publish . "/" . $baseName))
		{
			echo "Error! could not copy $file to $publish . "/" . $baseName\n";
		}
	}

	foreach($cunitXSL as $file)
	{
		$baseName = pathinfo($file,PATHINFO_BASENAME);
		if(!copy($file, $publish . "/" . $baseName))
		{
			echo "Error! could not copy $file to $publish . "/" . $baseName\n";
		}
	}
	// Copy the raw results
	foreach($testList as $unitTest)
	{
		if(!copy("$TR/../agents/$unitTest/tests/$unitTest.txt", "$publish/$unitTest.txt"))
		{
			echo "Error! could not copy $TR/agents/$unitTest/tests/$unitTest.txt to $publish\n";
		}
		// Copy the xml, if test didn't run, there should not be any xml files.
		$cpOut = shell_exec("cp $TR/../agents/$unitTest/tests/*.xml $publish 2>&1");
	}
} // copyResults

// cd to each agent test area, make clean and make and run the tests
//rewind($testList);

cd2TestRoot();
foreach($testList as $unitTest)
{
	if(!chdir("../agents/$unitTest/tests"))
	{
		echo "Error! cannot cd to  $TR/../agents/$unitTest/tests\n";
	}
	$clean = exec("make clean", $res, $crtn);
	if($crtn != 0)
	{
		echo "Error! make clean for $unitTest, skipping test\n";
		cd2TestRoot();
		continue;
	}
	$make = exec("make test > $unitTest.txt 2>&1", $res, $mrtn);
	if($mrtn != 0)
	{
		echo "Error! make test for $unitTest\n";
	}
}

// Check to see if unitTest directory exists, if not just copy results
// to cwd.

if(file_exists($publish))
{
	copyResults($publish);
}
else
{
	$publish = '.';
	copyResults($publish);
}

?>
