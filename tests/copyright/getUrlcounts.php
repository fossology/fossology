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
 * Get Simpletest urls and counts.  Parse the output of the copyright
 * agent and then search for those strings in an unpacked simpletest
 * archive.
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
		 * loaded under the name simpletest_1.0.1.tar.gz
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
		$this->assertTrue($this->myassertText($page, "/$safeName/"),
     "verifySimpleTest FAILED! Could not find simpletest_1.0.1.tar.gz upload\n");
		$result = $this->myassertText($page, "/$name/");
		if(!($result)) { exit(FALSE); }
	}

	function testgatherURLs()
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

		/* Select archive */
		$page = $this->mybrowser->clickLink($name);
		//print "************ Page after select foss archive *************\n$page\n";
		$this->assertTrue($this->myassertText($page, "/simpletest\//"),
      "verifySimpletest FAILED! 'simpletest/' not found\n");
		$this->assertTrue($this->myassertText($page, "/1 item/"),
      "verifySimpletest FAILED! '1 item' not found\n");

		/* Select the  Copyright/Email/URL link to get the copyright info */
		$browse = new parseBrowseMenu($page);
		$mini = new parseMiniMenu($page);
		$miniMenu = $mini->parseMiniMenu();
		//print "host is:$this->host\n";
		//print "mini-menu is:\n"; print_r($miniMenu) . "\n";
		$url = makeUrl($this->host, $miniMenu['Copyright/Email/URL']);
		if($url === NULL) { $this->fail("getURLcontents Failed, url not created"); }

		$page = $this->mybrowser->get($url);

		// get the 'Show' links, text and counts
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
			print "\$urlStd = array(\n";
			$total = 0;
			foreach($ct->hList as $list)
			{
				$cs = cleanString($list['textOrLink']);
				$findCmd = "find /usr/local/simpletest -type f -print | " .
				"xargs grep  \"'$cs'\"";
				$countCmd = $findCmd . ' | wc -l';
				
				$flast = exec("$findCmd", $foutput, $frtn);
				$clast = exec("$countCmd", $coutput, $crtn);
				print "'$cs' => $clast,\n";
				$total += $clast;
			}
			print ");\n";
			print "Total urls is:$total\n";
		}
	}
}
?>
