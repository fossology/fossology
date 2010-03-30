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
 * cliPkgagentTest.php
 * \brief test processing a single rpm from the command line
 *
 * @version "$Id: $"
 *
 * Created on March 11, 2010
 */

/* every test must use these includes, adjust the paths based on where the
 * tests are in the source tree.
 */
require_once ('../fossologyTestCase.php');
require_once ('../TestEnvironment.php');

/* 
 * Globals for test use. Need to set GlobalReady so require of 
 * pathinclude.php doesn't cause test to stop.
 */
global $GlobalReady;
$GlobalReady = 1;

global $AGENTDIR;

/* The class name should end in Test */

class cliPkgagentTest extends fossologyTestCase
{
	public $mybrowser;          // must have
	protected $testFile = 'TestData/fossology-1.1.0-1.centos4.i386.rpm';

	/*
	 */
	function setUp()
	{
		global $URL;
		$this->Login();
	}

	function testOneRpm()
	{
		print "starting testOneRpm\n";

		if(file_exists('/usr/local/share/fossology/php/pathinclude.php')){
			require('/usr/local/share/fossology/php/pathinclude.php');
		}else {
			if(file_exists('/usr/share/fossology/php/pathinclude.php')){
				require('/usr/share/fossology/php/pathinclude.php');
			}else {
				$this->fail("pkgagent FAILED! could not include fossology globals\n");
				exit(1);
			}
		}

		// run the pkgagent with an rpm
		
		$cmd = "$AGENTDIR/pkgagent $this->testFile";
		print "running $cmd\n";
		$last = exec($cmd, $output, $returnVal);
		//did it run with no error?
		if($returnVal != 0){
			$this->fail("pkgagent FAILED!, return value is:$returnVal\n");
		}else{
			$this->pass();
		}
	}

	function testOneRpmV()
	{
		print "starting testOneRpm -v\n";
		if(file_exists('/usr/local/share/fossology/php/pathinclude.php')){
			require('/usr/local/share/fossology/php/pathinclude.php');
		}else {
			if(file_exists('/usr/share/fossology/php/pathinclude.php')){
				require('/usr/share/fossology/php/pathinclude.php');
			}else {
				$this->fail("pkgagent FAILED! could not include fossology globals\n");
				exit(1);
			}
		}
		
		// run the pkgagent with an -v rpm, capture output
		
		$cmd = "$AGENTDIR/pkgagent -v $this->testFile";
		print "running $cmd\n";
		$last = exec($cmd, $output, $returnVal);
		//did it run with no error?
		if($returnVal != 0){
			$this->fail("pkgagent FAILED!, return value is:$returnVal\n");
		}else{
			$this->pass();
		}

		// check the output
		if(empty($output)){
			$this->fail("pkgagent FAILED!, no output for -v test, stopping test");
			exit(1);
		}
		// Errors in the output?
		//print "output before implode:\n";print_r($output) . "\n";
		$outString = implode("\n",$output);
		$numMatch = preg_match_all('/error/', $outString, $matches);
		if($numMatch != 0){
			$this->fail("pkgagent FAILED!, There were errors in the output\n");
			print "output is:\n$outString\n";
			print_r($matches) . "\n";
		}else {
			$this->pass();
		}
		
		// compare output to the standard
		/*look in the output for items that should be in the header
		* e.g.
		* Name:fossology
		* License:GPLv2
		* Summary:FOSSology is a licenses exploration tool
		* Size:37
		* Name:fossology-1.1.0-1.centos4.src.rpm
		*/
		$std = array('Name:fossology',
                  'License:GPLv2',
                  'Summary:FOSSology is a licenses exploration tool',
                  'Size:37',
                  'Name:fossology-1.1.0-1.centos4.src.rpm',
		);
		
		foreach($std as $match) {
			if(FALSE === in_array($match, $output)){
				$this->fail("pkgagent FAILED! did not fine $match in output\n");
			}else {
				$this->pass();
			}
		}
	}

	function testPkgAgentI()
	{
		print "starting testPkgagent -i\n";

		if(file_exists('/usr/local/share/fossology/php/pathinclude.php')){
			require('/usr/local/share/fossology/php/pathinclude.php');
		}else {
			if(file_exists('/usr/share/fossology/php/pathinclude.php')){
				require('/usr/share/fossology/php/pathinclude.php');
			}else {
				$this->fail("pkgagent FAILED! could not include fossology globals\n");
				exit(1);
			}
		}
		
		// run the pkgagent with -i option
		
		$cmd = "$AGENTDIR/pkgagent -i";
		print "running $cmd\n";
		$last = exec($cmd, $output, $returnVal);
		//did it run with no error?
		if($returnVal != 0){
			$this->fail("pkgagent FAILED!, return value is:$returnVal\n");
		}else{
			$this->pass();
		}
		if(!empty($output)) {
			$this->fail("pkgagent FAILED! output in -i test\n");
			print_r($output) . "\n";
		}
	}
}
?>
