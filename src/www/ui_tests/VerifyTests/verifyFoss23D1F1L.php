<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Verify special fossology test archive loaded correctly
 *
 * @version "$Id: verifyFoss23D1F1L.php 3464 2010-09-17 21:35:10Z rrando $"
 *
 * Created on Aug 25, 2008
 */

require_once('../../../tests/fossologyTestCase.php');
require_once('../../../tests/commonTestFuncs.php');
require_once('../../../tests/TestEnvironment.php');
require_once('../../../tests/testClasses/parseMiniMenu.php');
require_once('../../../tests/testClasses/parseFolderPath.php');
require_once('../../../tests/testClasses/dom-parseLicenseTable.php');

global $URL;

/**
 * This test verifies that the archive foss23D1F1L is processed
 * correctly.  The archive contains 23 directories (most empty),
 * 1 File and 1 license.
 */
class verify23D1F1L extends fossologyTestCase
{
	public $mybrowser;
	public $host;

	function setUp()
	{
		/*
		 * This test requires that the fossology test archive has been
		 * loaded under the name foss23D1F1L.tar.bz2
		 */
		global $URL;
		global $name;
		global $safeName;

		$name = 'foss23D1F1L.tar.bz2';
		$safeName = escapeDots($name);
		$this->host = getHost($URL);
		$this->Login();

		/* check for existense of archive */
		$page = $this->mybrowser->get($URL);
		$page = $this->mybrowser->clickLink('Browse');
		$this->assertTrue($this->myassertText($page, '/Browse/'),
     "verify23D1F1L FAILED! Could not find Browse menu\n");
		$page = $this->mybrowser->clickLink('Testing');
		$this->assertTrue($this->myassertText($page, '/Testing/'),
     "verify23D1F1L FAILED! Could not find Testing folder\n");
		$result = $this->myassertText($page, "/$name/");
		if(!($result)) { exit(FALSE); }
	}

	function testVerifyFossology()
	{
		global $URL;
		global $name;
		global $safeName;
		
		$licenseSummary = array(
    												'Unique licenses' 			 => 1,
    												'Licenses found'   			 => 1,
    												'Files with no licenses' => 0,
    												'Files'									 => 1
		);

		print "starting VerifyFoss23D1F1L test\n";
		$page = $this->mybrowser->clickLink('Browse');
		$this->assertTrue($this->myassertText($page, '/Browse/'),
             "verify23D1F1L FAILED! Could not find Browse menu\n");
		$page = $this->mybrowser->clickLink('Testing');
		//print "************ Page after upload link *************\n$page\n";
		$this->assertTrue($this->myassertText($page, "/Browse/"),
       "verify23D1F1L FAILED! Browse Title not found\n");
		$this->assertTrue($this->myassertText($page, "/$safeName/"),
       "verify23D1F1L FAILED! did not find $name\n");
		$this->assertTrue($this->myassertText($page, "/>View</"),
       "verifyfoss23D1F1L FAILED! >View< not found\n");
		$this->assertTrue($this->myassertText($page, "/>Info</"),
       "verifyFoss23D1F1L FAILED! >Info< not found\n");
		$this->assertTrue($this->myassertText($page, "/>Download</"),
       "verifyFoss23D1F1L FAILED! >Download< not found\n");

		/* Select archive */
		$page = $this->mybrowser->clickLink($name);
		//print "************ Page after select foss archive *************\n$page\n";
		$this->assertTrue($this->myassertText($page, "/foss23Dirs1File1Lic\//"),
      "verifyfoss23D1F1L FAILED! 'foss23Dirs1File1Lic/' not found\n");
		$this->assertTrue($this->myassertText($page, "/1 item/"),
      "verifyfoss23D1F1L FAILED! '1 item' not found\n");

		/* Select fossology link */
		$page = $this->mybrowser->clickLink('foss23Dirs1File1Lic/');

		/* need to check that there are 9 items */
		$this->assertTrue($this->myassertText($page, "/9 items/"),
      "verifyfoss23D1F1L FAILED! '9 items' not found\n");
		$this->assertTrue($this->myassertText($page, "/agents/"),
    "verify23D1F1L FAILED! did not find 'agents' directory\n");
		$this->assertTrue($this->myassertText($page, "/scheduler/"),
      "verify23D1F1L FAILED! did not find scheduler directory\n");

		/* Select the License link to View License Historgram */
		$mini = new parseMiniMenu($page);
		$miniMenu = $mini->parseMiniMenu();
		$url = makeUrl($this->host, $miniMenu['Nomos License']);
		if($url === NULL) { $this->fail("verify23D1F1L Failed, host is not set"); }

		$page = $this->mybrowser->get($url);
		//print "page after get of $url is:\n$page\n";
		$this->assertTrue($this->myassertText($page, '/Nomos License Browser/'),
          "verify23D1F1L FAILED! License Browser Title not found\n");
		
		// verify license summaries
	  $licSummary = new domParseLicenseTbl($page, 'licsummary', 0);
		$licSummary->parseLicenseTbl();
		
  	foreach ($licSummary->hList as $summary) {
  		$key = $summary['textOrLink'];
  		$this->assertEqual($licenseSummary[$key], $summary['count'],
  		"verifyFossDirsOnly FAILED! $key does not equal $licenseSummary[$key],
  		got $summary[count]\n");
			//print "summary is:\n";print_r($summary) . "\n";
		}

		// get the 'Show' links and License color links
		$licHisto = new domParseLicenseTbl($page, 'lichistogram');
		$licHisto->parseLicenseTbl();
		if(empty($licHisto->hList)) {
			$this->fail("FATAL! table with id=lichistogram was not found on
									the page, nothing to process, stopping test\n");
			exit(1);
		}
		/* Select show  'GPL_v2', verify */
		$gplv2URL = makeUrl($this->host, $licHisto->hList[1]['showLink']);
		$page = $this->mybrowser->get($gplv2URL);
		$licFileList = new parseFolderPath($page, $URL);
		$tblList = $licFileList->parseFolderPath();
		$fileCnt = count($tblList);
		print "Checking the number of files based on 'GPL_v2'\n";
		$this->assertTrue($fileCnt == 1,
		"FAILURE! Should only have 1 file with a GPL_v2 license: " .
		"Testing/foss23D1F1L.bz2/foss23Dirs1File1Lic/cli/cp2foss.php, " . 
		"got:$fileCnt\n");
	}
}
