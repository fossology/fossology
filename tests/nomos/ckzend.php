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
 * Verify zend license was processed correctly by nomos
 *
 * @param needs to have the zend-license uploaded.
 *
 * @return pass or fail
 *
 * @version "$Id$"
 *
 * Created on Sept. 14, 2010
 */

require_once ('../fossologyTestCase.php');
require_once ('../TestEnvironment.php');
require_once('../testClasses/parseMiniMenu.php');
require_once('../testClasses/parseBrowseMenu.php');
require_once('../commonTestFuncs.php');

/* Globals for test use, most tests need $URL, only login needs the others */
global $URL;

class zendTest extends fossologyTestCase
{
	public $mybrowser;          // must have
	public $someOtherVariable;
	protected $host;

	/*
	 * Every Test needs to login so we use the setUp method for that.
	 * setUp is called before any other method by default.
	 *
	 */
	function setUp()
	{
		global $URL;
		$this->Login();
		$this->host = getHost($URL);
	}

	function testZendLic()
	{
		global $URL;

		print "starting testZendLic\n";

		$name = 'zend-license';
		$page = $this->mybrowser->clickLink('Browse');
		$this->assertTrue($this->myassertText($page, "/>View</"),
       "ckzend FAILED! >View< not found\n");
		$this->assertTrue($this->myassertText($page, "/>Info</"),
       "ckzend FAILED! >Info< not found\n");
		$this->assertTrue($this->myassertText($page, "/>Download</"),
       "ckzend FAILED! >Download< not found\n");
		$page = $this->mybrowser->clickLink('Testing');
    $this->assertTrue($this->myassertText($page, '/Testing/'),
     "ckzend FAILED! Could not find Testing folder\n");
    $this->assertTrue($this->myassertText($page, "/$name/"),
       "ckzend FAILED! did not find $name\n");

		/* Select archive */
		$browse = new parseBrowseMenu($page, 'browsetbl', 1);
		$browse->parseBrowseMenuFiles();
		// get the View link for zend-license
		$viewLink = $browse->browseList[$name]['View'];
		$page = $this->mybrowser->get($viewLink);
		$mini = new parseMiniMenu($page);
		$miniMenu = $mini->parseMiniMenu();
		//print "miniMenu is:\n";print_r($miniMenu) . "\n";
		$url = makeUrl($this->host, $miniMenu['Nomos License']);
		if($url === NULL) { $this->fail("ckzend Failed, host/url is not set"); }

		$this->assertTrue($this->myassertText($page, '/View File/'),
          "ckzend FAILED! View File Title not found\n");
		$page = $this->mybrowser->get($url);
		// Check License 
		$this->assertTrue($this->myassertText($page,
		  '/The <b>Nomos<\/b> license detector found: <b>Zend_v2\.0<\/b>/'),
          "ckzend FAILED! Nomos license string does not match\n");
		$this->assertTrue($this->myassertText($page, '/View License/'),
          "ckzend FAILED! View License Title not found\n");
		// Check One-shot Analysis
		$urlOneShot = makeUrl($this->host, $miniMenu['Nomos One-Shot']);
		if($urlOneShot === NULL) { $this->fail("ckzend Failed, cannot make One-Shot url"); }
		$page = $this->mybrowser->get($urlOneShot);
		$this->assertTrue($this->myassertText($page, '/One-Shot License Analysis/'),
          "ckzend FAILED! One-Shot License Analysis Title not found\n");
		$osLicText = '<strong>Zend_v2\.0';
		$this->assertTrue($this->myassertText($page, "/$t/"),
          "ckzend FAILED! the text:\n$osLicText\n was not found\n");
	}
}
?>
