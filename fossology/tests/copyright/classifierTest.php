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
// regx to parse output of adams test program
// ^Test[0-9]+\spassed\s([0-9]+).*?([0-9]+)
/*
 * the above will not capture the test number my thoughts are if the two
 * captured numbers do not match then just print that line as part of the
 * failure.
 *
 * Need to cd to the copyright dir in the sources.
 */
/**
 * classifierTest
 * \brief run the python test that determines if the naive Bayes
 * classifier can correctly classify data that it has already seen.
 *
 * @version "$Id$"
 */

require_once('../fossologyTestCase.php');
require_once('../commonTestFuncs.php');
require_once('../TestEnvironment.php');
require_once('../testClasses/parseBrowseMenu.php');
require_once('../testClasses/parseMiniMenu.php');
require_once('../testClasses/parseFolderPath.php');
require_once('../testClasses/dom-parseLicenseTable.php');

global $URL;

class classifier extends fossologyTestCase
{
	public $mybrowser;
	public $host;

	function setUp()
	{

	}

	function testclassifier()
	{
		$path = TESTROOT;
		$classifierTest = "$path/../agents/copyright_analysis";
		if (chdir($classifierTest) === FALSE)
		{
			$this->fail("FATAL! can't cd to $classifierTest, stopping test\n");
			exit(1);
		}
		
		$last = exec("./tests.py", $results, $trtn);
		if($trtn != 0)
		{
			$this->fail("tests.py returned a non zero exit return, did it fail?\n");
		}
		//print "results of tests.py are:\n";print_r($results) . "\n";
		$pat = '/^Test[0-9]+\spassed\s([0-9]+).*?([0-9]+)/';
		
		// check results for test1
		$matches = preg_match_all($pat, $results[2], $found);
		//print "matches found are:\n";print_r($found) . "\n";
		if($results[1][0] != $results[2][0])
		{
			$this->fail("tests.py had test failures\n" .
									"test1 passes $results[1][0] differs from total:$results[2][0]\n");
		}
		else
		{
			$this->pass();
		}

		// check results for test2
		$matches = preg_match_all($pat, $results[6], $found);
		//print "matches found are:\n";print_r($found) . "\n";
		if($results[1][0] != $results[2][0])
		{
			$this->fail("tests.py had test failures\n" .
									"test2 passes $results[1][0] differs from total:$results[2][0]\n");
		}
		else
		{
			$this->pass();
		}
	}
}
?>