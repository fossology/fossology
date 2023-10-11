<?php
/*
 SPDX-FileCopyrightText: © 2010 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * print the copyright table for 3files as a php array
 *
 * @version "$Id: printLicTbl.php 3682 2010-11-18 01:33:52Z rrando $"
 *
 * Created on Sept. 30, 2010, really just a hack of printURL.php, need
 * to generalize it:
 */

require_once('../fossologyTestCase.php');
require_once('../commonTestFuncs.php');
require_once('../TestEnvironment.php');
require_once('../testClasses/parseBrowseMenu.php');
require_once('../testClasses/parseMiniMenu.php');
require_once('../testClasses/parseFolderPath.php');
require_once('../testClasses/dom-parseLicenseTable.php');
require_once('../copyright/libCopyRight.php');

global $URL;

/**
 * print a table from the UI, so it can be used in a test.
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

		$name = 'RedHat.tar.gz';
		$safeName = escapeDots($name);
		$this->host = getHost($URL);
		//print "SetUp: host is:$this->host\n";
		$this->Login();

		/* check for existense of archive */
		$page = $this->mybrowser->get($URL);
		$page = $this->mybrowser->clickLink('Browse');
		$this->assertTrue($this->myassertText($page, '/Browse/'),
     "verifySimpletest FAILED! Could not find Browse menu\n");
		$page = $this->mybrowser->clickLink('Testing');
		$this->assertTrue($this->myassertText($page, "/$safeName/"),
     "verifySimpleTest FAILED! Could not find RedHat.tar upload\n");
		$result = $this->myassertText($page, "/$name/");
		//if(!($result)) { echo "WTF!\n"; exit(FALSE); }
	}

	function testLicTbl()
	{
		global $URL;
		global $name;
		global $safeName;


		print "starting print lics\n";
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
		$page = $this->mybrowser->clickLink('Testing');
		$page = $this->mybrowser->clickLink($name);
		//print "************ Page after select RedHat.tar *************\n$page\n";
		$this->assertTrue($this->myassertText($page, "/1 item/"),
      "verifySimpletest FAILED! '1 item' not found\n");
		$this->assertTrue($this->myassertText($page, "/RedHat/"),
      "verifySimpletest FAILED! 'RedHat' not found\n");
		$page = $this->mybrowser->clickLink('RedHat/');
		//print "************ Page after select RedHat Link *************\n$page\n";

		/* Select the link to get copyright info */
		$mini = new parseMiniMenu($page);
		$miniMenu = $mini->parseMiniMenu();
		//print "MiniMenu is:\n";print_r($miniMenu) . "\n";
		$url = makeUrl($this->host, $miniMenu['Nomos License']);
		if($url === NULL) { $this->fail("RHEL Lics Failed, host/URL is not set"); }

		$page = $this->mybrowser->get($url);

		// get the 'Show' links and License color links
		$ct = new domParseLicenseTbl($page, 'lichistogram');
		$ct->parseLicenseTbl();
		// empty table? Error
		if($ct->noRows) {
			$this->fail("Error! table with id=copyright has no rows!" .
									" nothing to process, There should be!\n");
		}
		else if(empty($ct->hList)) {
			$this->fail("Error! table with id=copyright was not found on" .
									"the page, There should be one\n");
		}
		else {
			$total = 0;
			print "\$copyStd = array(\n";
			foreach($ct->hList as $list)
			{
				$cs = cleanString($list['textOrLink']);
				print "    '$cs' => $list[count],\n";
				$total += (int)$list['count'];
			}
			print ");\n";
		}
	}
}
