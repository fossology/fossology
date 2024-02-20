<?php
/*
 SPDX-FileCopyrightText: © 2010 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * print the url table for simple test as a php array
 *
 * @version "$Id:  $"
 *
 * Created on March 19, 2010
 */

require_once('../fossologyTestCase.php');
require_once('../commonTestFuncs.php');
require_once('../TestEnvironment.php');
require_once('../testClasses/parseBrowseMenu.php');
require_once('../testClasses/parseMiniMenu.php');
require_once('../testClasses/parseFolderPath.php');
require_once('../testClasses/dom-parseLicenseTable.php');
require_once('libCopyRight.php');

global $URL;

/**
 * This test verifies that the archive 3files.tar.bz2 contains the correct set
 * of copyrights, emails and urls.
 */
class verifySimpletest extends fossologyTestCase
{
	public $mybrowser;
	public $host;

	function setUp()
	{
		/*
		 * This test requires that the fossology test archive has been
		 * loaded under the name 3files.tar.bz2
		 */
		global $URL;
		global $name;
		global $safeName;

		$name = 'simpletest_1.0.1.tar.gz';
		$safeName = escapeDots($name);
		$this->host = getHost($URL);
		$this->Login();

		/* check for existense of archive */
		$page = $this->mybrowser->get($URL);
		$page = $this->mybrowser->clickLink('Browse');
		$this->assertTrue($this->myassertText($page, '/Browse/'),
     "verifySimpletest FAILED! Could not find Browse menu\n");
		$this->assertTrue($this->myassertText($page, "/$safeName/"),
     "verifySimpleTest FAILED! Could not find simpletest_1.0.1.tar.gz upload\n");
		$result = $this->myassertText($page, "/$name/");
		if(!($result)) { exit(FALSE); }
	}

	function testSimpletest()
	{
		global $URL;
		global $name;
		global $safeName;


		print "starting print url's\n";
		$page = $this->mybrowser->clickLink('Browse');
		$this->assertTrue($this->myassertText($page, '/Browse/'),
             "verifySimpleTest FAILED! Could not find Browse menu\n");
		$this->assertTrue($this->myassertText($page, "/Browse/"),
       "verifySimpleTest FAILED! Browse Title not found\n");
		$this->assertTrue($this->myassertText($page, "/$safeName/"),
       "verifySimpleTest FAILED! did not find $name\n");
		$this->assertTrue($this->myassertText($page, "/>View</"),
       "verifySimpletest FAILED! >View< not found\n");
		$this->assertTrue($this->myassertText($page, "/>Info</"),
       "verifySimpletest FAILED! >Info< not found\n");
		$this->assertTrue($this->myassertText($page, "/>Download</"),
       "verifySimpletest FAILED! >Download< not found\n");

		/* Select archive */
		$page = $this->mybrowser->clickLink($name);
		//print "************ Page after select foss archive *************\n$page\n";
		$this->assertTrue($this->myassertText($page, "/simpletest\//"),
      "verifySimpletest FAILED! 'simpletest/' not found\n");
		$this->assertTrue($this->myassertText($page, "/1 item/"),
      "verifySimpletest FAILED! '1 item' not found\n");

		/* Select the link to get copyright info */
		$browse = new parseBrowseMenu($page);
		$mini = new parseMiniMenu($page);
		$miniMenu = $mini->parseMiniMenu();
		$url = makeUrl($this->host, $miniMenu['Copyright/Email/URL']);
		if($url === NULL) { $this->fail("verifySimpletest Failed, host is not set"); }

		$page = $this->mybrowser->get($url);

		// get the 'Show' links and License color links
		$ct = new domParseLicenseTbl($page, 'copyrighturl');
		$ct->parseLicenseTbl();
		// empty table? Error
		if($ct->noRows) {
			$this->fail("Error! table with id=copyrighturl has no rows!" .
									" nothing to process, There should be!\n");
		}
		else if(empty($ct->hList)) {
			$this->fail("Error! table with id=copyrighturl was not found on" .
									"the page, There should be one\n");
		}
		else {
			$total = 0;
			print "\$urlStd = array(\n";
			foreach($ct->hList as $list) 
			{
				$cs = cleanString($list['textOrLink']);
				print "\t\t'$cs' => $list[count],\n";
				$total += (int)$list['count'];
			}
			print ");\n";
			print "Total URLs: $total\n";
		}
	}
}
