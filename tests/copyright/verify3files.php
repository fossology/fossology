<?php
/***********************************************************
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
 ***********************************************************/

/**
 * Verify special fossology test archive loaded correctly
 *
 * @version "$Id$"
 *
 * Created on March 17, 2010
 */

require_once('../fossologyTestCase.php');
require_once('../commonTestFuncs.php');
require_once('../TestEnvironment.php');
require_once('../testClasses/parseBrowseMenu.php');
require_once('../testClasses/parseMiniMenu.php');
require_once('../testClasses/parseFolderPath.php');
require_once('../testClasses/dom-parseLicenseTable.php');

global $URL;

/**
 * This test verifies that the archive 3files.tar.bz2 contains the correct set
 * of copyrights, emails and urls.
 */
class verify3filesCopyright extends fossologyTestCase
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

		$name = '3files.tar.bz2';
		$safeName = escapeDots($name);
		$this->host = getHost($URL);
		$this->Login();

		/* check for existense of archive */
		$page = $this->mybrowser->get($URL);
		$page = $this->mybrowser->clickLink('Browse');
		$this->assertTrue($this->myassertText($page, '/Browse/'),
     "verify3files FAILED! Could not find Browse menu\n");
		$page = $this->mybrowser->clickLink('copyright');
		$this->assertTrue($this->myassertText($page, '/Copyright/'),
     "verify3files FAILED! Could not find copyright folder\n");
		$result = $this->myassertText($page, "/$name/");
		if(!($result)) { exit(FALSE); }
	}

	function test3filesCopyright()
	{
		global $URL;
		global $name;
		global $safeName;

		print "starting Verify3filesCopyright test\n";
		$page = $this->mybrowser->clickLink('Browse');
		$this->assertTrue($this->myassertText($page, '/Browse/'),
             "verify3files FAILED! Could not find Browse menu\n");
		$page = $this->mybrowser->clickLink('copyright');
		//print "************ Page after upload link *************\n$page\n";
		$this->assertTrue($this->myassertText($page, "/Browse/"),
       "verify3files FAILED! Browse Title not found\n");
		$this->assertTrue($this->myassertText($page, "/$safeName/"),
       "verify3files FAILED! did not find $name\n");
		$this->assertTrue($this->myassertText($page, "/>View</"),
       "verify3files FAILED! >View< not found\n");
		$this->assertTrue($this->myassertText($page, "/>Meta</"),
       "verify3files FAILED! >Meta< not found\n");
		$this->assertTrue($this->myassertText($page, "/>Download</"),
       "verify3files FAILED! >Download< not found\n");

		/* Select archive */
		$page = $this->mybrowser->clickLink($name);
		//print "************ Page after select foss archive *************\n$page\n";
		$this->assertTrue($this->myassertText($page, "/Affero-v1\.0/"),
      "verify3files FAILED! '3files/' not found\n");
		$this->assertTrue($this->myassertText($page, "/3 items/"),
      "verify3files FAILED! '1 item' not found\n");


		/* Select the License link to View License Historgram */
		$browse = new parseBrowseMenu($page);
		$mini = new parseMiniMenu($page);
		$miniMenu = $mini->parseMiniMenu();
		$url = makeUrl($this->host, $miniMenu['Copyright/Email/Url']);
		if($url === NULL) { 
			$this->fail("verify3files Failed, host is not set or url cannot be made"); 
		}

		$page = $this->mybrowser->get($url);
		//print "page after get of $url is:\n$page\n";
		$this->assertTrue($this->myassertText($page, '/Copyright\/Email\/Url Browser/'),
          "verify3files FAILED! Copyright/Email/Url Browser Title not found\n");
		$this->assertTrue($this->myassertText($page, '/Total Copyrights: 3/'),
        "verify3files FAILED! Total copyrights does not equal 3\n");

		// get the count, show links and text or link
		$copyR = new domParseLicenseTbl($page, 'copyright');
		$copyR->parseLicenseTbl();
		if(empty($copyR->hList)) {
			$this->fail("FATAL! table with id=copyright was not found on" .
									"the page, nothing to process, stopping test\n");
			exit(1);
		}

		$email = new domParseLicenseTbl($page, 'copyrightemail');
		$email->parseLicenseTbl();
		// empty table?, verify counts are zero
		if($email->noRows) {
			$this->assertTrue($this->myassertText($page, '/Total Emails: 0/'),
        "verify3files FAILED! Total Emails does not equal 0\n");
			$this->assertTrue($this->myassertText($page, '/Unique Emails: 0/'),
        "verify3files FAILED! Unique Emails does not equal 0\n");
		}
		// noRows found, if list is empty, could not find the table on the page.
		// This is a fatal error.
		else if(empty($email->hList)) {
			$this->fail("FATAL! table with id=copyrightemail was not found on" .
									"the page, nothing to process, stopping test\n");
			exit(1);
		}
		$urls = new domParseLicenseTbl($page, 'copyrighturl');
		$urls->parseLicenseTbl();
		// empty table?, verify counts are zero
		if($urls->noRows) {
			$this->assertTrue($this->myassertText($page, '/Total URLs: 0/'),
        "verify3files FAILED! Total URL's does not equal 0\n");
			$this->assertTrue($this->myassertText($page, '/Unique URLs: 0/'),
        "verify3files FAILED! Unique URL's does not equal 0\n");
		}
		// noRows found, if list is empty, could not find the table on the page.
		// This is a fatal error.
		else if(empty($urls->hList)) {
			$this->fail("FATAL! table with id=copyrighturl was not found on" .
									"the page, nothing to process, stopping test\n");
			exit(1);
		}
	}
}
?>
