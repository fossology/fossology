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
 * @version "$Id: $"
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
		$this->assertTrue($this->myassertText($page, "/$safename/"),
     "verifySimpleTest FAILED! Could not find simpletest_1.0.1.tar.gz upload\n");
		$result = $this->myassertText($page, "/$name/");
		if(!($result)) { exit(FALSE); }
	}

	function testSimpletest()
	{
		global $URL;
		global $name;
		global $safeName;
		
		$copyRights = array('1' =>'(C) 1991, 1999 Free Software Foundation, Inc. 51');
		$emailStd   = array('4' => 'secret@www.lastcraft.com',
												'2' => 'marcus@lastcraft.com',
												'2' => 'name@example.com',
												'2' => 'password@www.somewhere.com',
												'2' => 'secret@host.com',
												'2' => 'secret@this.com',
												'2' => 'secret@www.here.com',
												'2' => 'simpletest-support@lists.sourceforge.net',
												'1' => 'development@domain51.com',
												'1' => 'password@somewhere.com',
												'1' => 'secret@this.host',
												'1' => 'username@www.somewhere.com'
												);

		print "starting VerifySimpletest test\n";
		$page = $this->mybrowser->clickLink('Browse');
		$this->assertTrue($this->myassertText($page, '/Browse/'),
             "verifySimpleTest FAILED! Could not find Browse menu\n");
		$this->assertTrue($this->myassertText($page, "/Browse/"),
       "verifySimpleTest FAILED! Browse Title not found\n");
		$this->assertTrue($this->myassertText($page, "/$safeName/"),
       "verifySimpleTest FAILED! did not find $name\n");
		$this->assertTrue($this->myassertText($page, "/>View</"),
       "verifySimpletest FAILED! >View< not found\n");
		$this->assertTrue($this->myassertText($page, "/>Meta</"),
       "verifySimpletest FAILED! >Meta< not found\n");
		$this->assertTrue($this->myassertText($page, "/>Download</"),
       "verifySimpletest FAILED! >Download< not found\n");

		/* Select archive */
		$page = $this->mybrowser->clickLink($name);
		//print "************ Page after select foss archive *************\n$page\n";
		$this->assertTrue($this->myassertText($page, "/simpletest\//"),
      "verifySimpletest FAILED! 'simpletest/' not found\n");
		$this->assertTrue($this->myassertText($page, "/1 item/"),
      "verifySimpletest FAILED! '1 item' not found\n");


		/* Select the License link to View License Historgram */
		$browse = new parseBrowseMenu($page);
		$mini = new parseMiniMenu($page);
		$miniMenu = $mini->parseMiniMenu();
		$url = makeUrl($this->host, $miniMenu['Copyright/Email/Url']);
		if($url === NULL) { $this->fail("verifySimpletest Failed, host is not set"); }

		$page = $this->mybrowser->get($url);
		//print "page after get of $url is:\n$page\n";
		$this->assertTrue($this->myassertText($page, '/Copyright\/Email\/Url Browser/'),
          "verifySimpleTest FAILED! Copyright/Email/Url Browser Title not found\n");
		$this->assertTrue($this->myassertText($page, '/Total Copyrights: 1/'),
        "verifySimpleTest FAILED! Total copyrights does not equal 1\n");

		// get the 'Show' links and License color links
		$ct = new domParseLicenseTbl($page, 'copyright');
		$ct->parseLicenseTbl();
		// empty table? check no copyrights.
		if($ct->noRows) {
			$this->assertTrue($this->myassertText($page, '/Total Copyrights: 0/'),
        "verifySimpleTest FAILED! Total copyrights does not equal 0\n");
			$this->assertTrue($this->myassertText($page, '/Unique Copyrights: 0/'),
        "verifySimpleTest FAILED! Total copyrights does not equal 0\n");
		}
		else if(empty($ct->hList)) {
			$this->fail("FATAL! table with id=copyright was not found on" .
									"the page, nothing to process, stopping test\n");
			exit(1);
		}
		else {
			//print "copyright list is:\n"; print_r($ct->hList) . "\n";
			foreach($ct->hList as $list) {
				//print "list is:$list\n";print_r($list) . "\n";
				foreach($copyRights as $ccount => $text){
					// make sure there are no imbeded new lines
					$cleanText = str_replace("\n", '', $list['textOrLink']);
					$trimct = trim($cleanText);
					$trimText = trim($text);
					if($trimct == $trimText) {
						$this->pass();
					}
					else {
						$this->fail("verifySimpleTest FAILED! Should be $trimText " .
    	 			"based on the Standard got:$trimct");
					}
					$this->assertEqual($ccount, $list['count'],
    			"verifySimpleTest FAILED! Should be $list[count] files
    	 		based on Standard $ccount got:$list[count]\n");
				}
			}
		}

		$email = new domParseLicenseTbl($page, 'copyrightemail');
		$email->parseLicenseTbl();
		if(empty($email->hList)) {
			$this->fail("FATAL! table with id=copyrightemail was not found on" .
									"the page, nothing to process, stopping test\n");
			exit(1);
		}
		//print "email list is:\n"; print_r($email->hList) . "\n";
	}
}
?>
