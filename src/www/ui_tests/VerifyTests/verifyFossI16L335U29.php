<?php
/*
 SPDX-FileCopyrightText: © 2010 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Verify special fossology test archive loaded correctly
 *
 * @version "$Id: verifyFossI16L335U29.php 3688 2010-11-20 00:05:14Z rrando $"
 *
 * Created on March 10, 2010
 */

require_once('../../../tests/fossologyTestCase.php');
require_once('../../../tests/commonTestFuncs.php');
require_once('../../../tests/TestEnvironment.php');
require_once('../../../tests/testClasses/parseMiniMenu.php');
require_once('../../../tests/testClasses/parseFolderPath.php');
//require_once('../../../tests/testClasses/parseLicenseTbl.php');
require_once('../../../tests/testClasses/dom-parseLicenseTable.php');

global $URL;

class verifyFossolyTest extends fossologyTestCase
{
	public $mybrowser;
	public $host;

	function setUp()
	{
		/*
		 * This test requires that the fossology test archive has been
		 * loaded under the name fossI16L335U29.tar.bz2
		 */
		global $URL;
		global $name;
		global $safeName;

		$name = 'fossI16L335U29.tar.bz2';
		$safeName = escapeDots($name);
		$this->host = getHost($URL);

		$this->Login();

		/* check for existense of archive */
		$page = $this->mybrowser->get($URL);
		$page = $this->mybrowser->clickLink('Browse');
		$this->assertTrue($this->myassertText($page, '/Browse/'),
     "verifyFossl16L335 FAILED! Could not find Browse menu\n");
		$page = $this->mybrowser->clickLink('Testing');
		$this->assertTrue($this->myassertText($page, '/Testing/'),
     "verifyFossl16L335 FAILED! Could not find Testing folder\n");
		$result = $this->myassertText($page, "/$safeName/");
		if(!($result)) { exit(FALSE); }
	}

	function testVerifyFossl16L335()
	{
		global $URL;
		global $name;
		global $safeName;

		// licenseCounts recorded 2010-11-19 for release 1.3
		$licenseCounts = array(
     'GPL_v2' => 224,
    'No_license_found' => 72,
    'GPL' => 23,
    'LGPL_v2.1' => 17,
    'Apache_v2.0' => 2,
    'GFDL' => 2,
    'Public-domain' => 8,
    'APSL_v2.0' => 1,
    'Artistic' => 1,
    'Boost' => 1,
    'BSD' => 1,
    'FSF-possibility' => 1,
    'GPL_v2-possibility' => 1,
    'LGPL_v2.1+' => 1,
    'LGPL_v3+' => 1,
    'NPL' => 1,
    'OSL_v3.0' => 1,
    'PHP-possibility' => 1,
    'Python' => 1,
    'See-doc(OTHER)' => 1,
    'X11-possibility' => 1,
    'Zope' => 1,
);

		$licenseSummary = array(
      'Unique licenses'=> 22,
      'Licenses found'=> 291,
      'Files with no licenses'=> 72,
      'Files'=> 345
		);

		print "starting VerifyFossl16L335 test\n";
		$page = $this->mybrowser->clickLink('Browse');
		$this->assertTrue($this->myassertText($page, '/Browse/'),
             "verifyFossl16L335 FAILED! Could not find Browse menu\n");
		/* Testing folder */
		$page = $this->mybrowser->clickLink('Testing');
		//print "************ Page after upload link *************\n$page\n";
		$this->assertTrue($this->myassertText($page, "/Browse/"),
       "verifyFossl16L335 FAILED! Browse Title not found\n");
		$this->assertTrue($this->myassertText($page, "/$safeName/"),
       "verifyFossl16L335 FAILED! did not find $name\n");
		$this->assertTrue($this->myassertText($page, "/>View</"),
       "verifyFossl16L335 FAILED! >View< not found\n");
		$this->assertTrue($this->myassertText($page, "/>Info</"),
       "verifyFossl16L335 FAILED! >Info< not found\n");
		$this->assertTrue($this->myassertText($page, "/>Download</"),
       "verifyFossl16L335 FAILED! >Download< not found\n");

		/* Select archive */
		$page = $this->mybrowser->clickLink($name);
		//print "************ Page after select foss archive *************\n$page\n";
		$this->assertTrue($this->myassertText($page, "/fossI16L335U29\.tar/"),
		  "FAILURE! Could not find fossI16L335U29.tar link\n");

		/* Select fossology link */
		$page = $this->mybrowser->clickLink('fossI16L335U29.tar');
		$page = $this->mybrowser->clickLink('fossology/');

		/* need to check that there are 16 items */
		/* check that all the [xxx] items add to 335 */

		$this->assertTrue($this->myassertText($page, "/Makefile/"));
		$this->assertTrue($this->myassertText($page, "/mkcheck\.sh/"),
                      "FAIL! did not find mkcheck.sh\n");
		$this->assertTrue($this->myassertText($page, "/>View</"),
                      "FAIL! >View< not found\n");
		$this->assertTrue($this->myassertText($page, "/>Info</"),
                      "FAIL! >Info< not found\n");
		$this->assertTrue($this->myassertText($page, "/>Download</"),
                      "FAIL! >Download< not found\n");

		/* Select the License link to View License Historgram */
		$mini = new parseMiniMenu($page);
		$miniMenu = $mini->parseMiniMenu();
		$url = makeUrl($this->host, $miniMenu['License Browser']);
		if($url === NULL) { $this->fail("verifyFossl16L335 Failed, host/url is not set"); }

		$page = $this->mybrowser->get($url);
		//print "page after get of $url is:\n$page\n";
		$this->assertTrue($this->myassertText($page, '/License Browser/'),
          "verifyFossl16L335 FAILED! Nomos License Browser Title not found\n");

		// check that license summarys are correct
		$licSummary = new domParseLicenseTbl($page, 'licsummary', 0);
		$licSummary->parseLicenseTbl();

		foreach ($licSummary->hList as $summary) {
			$key = $summary['textOrLink'];
			$this->assertEqual($licenseSummary[$key], $summary['count'],
  		"verifyFossl16L335 FAILED! $key does not equal $licenseSummary[$key],
  		got $summary[count]\n");
			//print "summary is:\n";print_r($summary) . "\n";
		}

		// get the license names and 'Show' links
		$licHistogram = new domParseLicenseTbl($page, 'lichistogram',1);
		$licHistogram->parseLicenseTbl();

		if($licHistogram->noRows === TRUE)
		{
			$this->fail("FATAL! no table rows to process, there should be many for"
			. " this test, Stopping the test");
			return;
		}

		// verify every row against the standard by comparing the counts.
		/*
		* @todo check the show links, but to do that, need to gather another
		* standard array to match against  or just use the count from the
		* baseline?
		*/

		foreach($licHistogram->hList as $licFound)
		{
			$key = $licFound['textOrLink'];
			//print "VDB: key is:$key\n";
			//print "licFound is:\n";print_r($licFound) . "\n";
			if(array_key_exists($key,$licenseCounts))
			{
				$this->assertEqual($licenseCounts[$key], $licFound['count'],
          "verifyFossl16L335 FAILED! the baseline count {$licenseCounts[$key]} does" .
          " not equal {$licFound['count']} for license $key,\n" .
          "Expected: {$licenseCounts[$key]},\n" .
          "     Got: {$licFound['count']}\n");
			}
			else
			{
				$this->fail("verifyFossl16L335 A License was found that is " .
         "not in the standard:\n$key\n");
			}
		}
	}
}
