<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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
 * @version "$Id: browseUploadedTest.php 1153 2008-08-21 03:59:33Z rrando $"
 *
 * Created on Aug 25, 2008
 */

require_once('../../../../tests/fossologyWebTestCase.php');
require_once('../../../../tests/TestEnvironment.php');
require_once('../../../../tests/testClasses/parseBrowseMenu.php');
require_once('../../../../tests/testClasses/parseMiniMenu.php');
require_once('../../../../tests/testClasses/parseLicFileList.php');
require_once('../../../../tests/testClasses/parseLicenseTbl.php');

global $URL;

class verifyFossolyTest extends fossologyWebTestCase
{
  public $mybrowser;
  public $host;

  function setUp()
  {
    /*
     * This test requires that the fossology test archive has been
     * loaded under the name fossarchive-T.tar.bz2 For now, the setup
     * will just verify the material is there?
     */
    global $URL;

    $browser = & new SimpleBrowser();
    $page = $browser->get($URL);
    $this->assertTrue($page);
    $this->assertTrue(is_object($browser));
    $this->mybrowser = $browser;
    $cookie = $this->repoLogin($this->mybrowser);
    $this->host = $this->getHost($URL);
    $this->mybrowser->setCookie('Login', $cookie, $host);
    /* still need to check for existense of archive */
  }

  function testVerifyFossology()
  {
    global $URL;

    $name = 'fossarchive-T\.tar\.bz2';

    print "starting VerifyFossology test\n";
    $page = $this->mybrowser->get("$URL?mod=browse");
    $this->assertTrue($this->assertText($page, '/Browse/'),
                      "FAIL! Could not find Browse menu\n");

    /* Testing folder */
    $page = $this->mybrowser->clickLink('Testing');
    //print "************ Page after upload link *************\n$page\n";
    $this->assertTrue($this->assertText($page, "/Browse/"),
                      "FAIL! Browse Title not found\n");
    $this->assertTrue($this->assertText($page, "/$name/"),
                      "FAIL! did not find fossarchive-T.tar.bz2\n");
    $this->assertTrue($this->assertText($page, "/>View</"),
                      "FAIL! >View< not found\n");
    $this->assertTrue($this->assertText($page, "/>Meta</"),
                      "FAIL! >Meta< not found\n");
    $this->assertTrue($this->assertText($page, "/>Download</"),
                      "FAIL! >Download< not found\n");

    /* Select 'fossarchive-T.tar.bz2' */
    $page = $this->mybrowser->clickLink('fossarchive-T.tar.bz2');
    //print "************ Page after select foss archive *************\n$page\n";
    $this->assertTrue($this->assertText($page, "/fossology\//"));

    /* Select fossology link */
    $page = $this->mybrowser->clickLink('fossology/');

    $this->assertTrue($this->assertText($page, "/Makefile/"));
    $this->assertTrue($this->assertText($page, "/mkcheck\.sh/"),
                      "FAIL! did not find mkcheck.sh\n");
    $this->assertTrue($this->assertText($page, "/>View</"),
                      "FAIL! >View< not found\n");
    $this->assertTrue($this->assertText($page, "/>Meta</"),
                      "FAIL! >Meta< not found\n");
    $this->assertTrue($this->assertText($page, "/>Download</"),
                      "FAIL! >Download< not found\n");

    /* Select the License link to View License Historgram */
    $browse = new parseBrowseMenu($page);
    $mini = new parseMiniMenu($page);
    $miniMenu = $mini->parseMiniMenu();
    $url = $this->makeUrl($this->host, $miniMenu['License']);
    $page = $this->mybrowser->get($url);
    $this->assertTrue($this->assertText($page, '/License Browser/'),
                      "FAIL! License Browser not found\n");
    $this->assertTrue($this->assertText($page, '/Total licenses: 499/'),
                      "FAIL! Total Licenses does not equal 499\n");

    // get the 'Show' links and License color links
    $licTbl = new parseLicenseTbl($page);
    $licTable = $licTbl->parseLicenseTbl();

    /* Select show 'Public Domain, verify, select 'LGPL v2.1', verify */
    $pdURL = $this->makeUrl($this->host, $licTable['Public Domain'][0]);
    $lgplURL = $this->makeUrl($this->host, $licTable['LGPL v2.1'][0]);

    $page = $this->mybrowser->get($pdURL);
    $licFileList = new parseLicFileList($page);
    $tblList = $licFileList->parseLicFileList();
    $tableCnt = count($tblList);
    $this->assertEqual($tableCnt, 5);

    $page = $this->mybrowser->get($lgplURL);
    $licFileList->setPage($page);
    $flist = $licFileList->parseLicFileList();
    $flistCnt = count($flist);
    $this->assertEqual($flistCnt, 3);
  }
}
?>
