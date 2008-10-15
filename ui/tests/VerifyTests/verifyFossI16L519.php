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

require_once('../../../tests/fossologyTestCase.php');
require_once('../../../tests/TestEnvironment.php');
require_once('../../../tests/testClasses/parseBrowseMenu.php');
require_once('../../../tests/testClasses/parseMiniMenu.php');
require_once('../../../tests/testClasses/parseLicFileList.php');
require_once('../../../tests/testClasses/parseLicenseTbl.php');

global $URL;

class verifyFossolyTest extends fossologyTestCase
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

    $this->host = $this->getHost($URL);

    $this->Login();

    /* check for existense of archive */
    $page = $this->mybrowser->get($URL);
    $page = $this->mybrowser->clickLink('Browse');
    $this->assertTrue($this->myassertText($page, '/Browse/'),
     "verifyFossTestAr FAILED! Could not find Browse menu\n");
    $page = $this->mybrowser->clickLink('Testing');
    $this->assertTrue($this->myassertText($page, '/Testing/'),
     "verifyFossTestAr FAILED! Could not find Testing folder\n");
    $result = $this->myassertText($page, "/$name/");
    if(!($result)) { exit(FALSE); }
  }

  function testVerifyFossology()
  {
    global $URL;

    // change the name to reflect the new results.
    $name = 'fossI16L519.tar.bz2';
    $safeName = $this->escapeDots($name);

    print "starting VerifyFossology test\n";
    $page = $this->mybrowser->clickLink('Browse');
    $this->assertTrue($this->myassertText($page, '/Browse/'),
             "verifyFossTestAr FAILED! Could not find Browse menu\n");
    /* Testing folder */
    $page = $this->mybrowser->clickLink('Testing');
    //print "************ Page after upload link *************\n$page\n";
    $this->assertTrue($this->myassertText($page, "/Browse/"),
       "verifyFossTestAr FAILED! Browse Title not found\n");
    $this->assertTrue($this->myassertText($page, "/$safeName/"),
       "verifyFossTestAr FAILED! did not find $name\n");
    $this->assertTrue($this->myassertText($page, "/>View</"),
       "verifyFossTestAr FAILED! >View< not found\n");
    $this->assertTrue($this->myassertText($page, "/>Meta</"),
       "verifyFossTestAr FAILED! >Meta< not found\n");
    $this->assertTrue($this->myassertText($page, "/>Download</"),
       "verifyFossTestAr FAILED! >Download< not found\n");

    /* Select archive */
    $page = $this->mybrowser->clickLink($name);
    //print "************ Page after select foss archive *************\n$page\n";
    $this->assertTrue($this->myassertText($page, "/fossology\//"));

    /* Select fossology link */
    $page = $this->mybrowser->clickLink('fossology/');

    /* need to check that there are 16 items */
    /* check that all the [xxx] items add to 519 */

    $this->assertTrue($this->myassertText($page, "/Makefile/"));
    $this->assertTrue($this->myassertText($page, "/mkcheck\.sh/"),
                      "FAIL! did not find mkcheck.sh\n");
    $this->assertTrue($this->myassertText($page, "/>View</"),
                      "FAIL! >View< not found\n");
    $this->assertTrue($this->myassertText($page, "/>Meta</"),
                      "FAIL! >Meta< not found\n");
    $this->assertTrue($this->myassertText($page, "/>Download</"),
                      "FAIL! >Download< not found\n");

    /* Select the License link to View License Historgram */
    $browse = new parseBrowseMenu($page);
    $mini = new parseMiniMenu($page);
    $miniMenu = $mini->parseMiniMenu();
    $url = $this->makeUrl($this->host, $miniMenu['License']);
    if($url === NULL) { $this->fail("verifyFossTestAr Failed, host is not set"); }

    $page = $this->mybrowser->get($url);
    //print "page after get of $url is:\n$page\n";
    $this->assertTrue($this->myassertText($page, '/License Browser/'),
          "verifyFossTestAr FAILED! License Browser Title not found\n");
    $this->assertTrue($this->myassertText($page, '/Total licenses: 519/'),
        "verifyFossTestAr FAILED! Total Licenses does not equal 519\n");

    // get the 'Show' links and License color links
    $licTbl = new parseLicenseTbl($page);
    $licTable = $licTbl->parseLicenseTbl();
    //print "licTable is:\n"; print_r($licTable) . "\n";

    /* FIX THIS Select show 'Public Domain, verify, select 'LGPL v2.1', verify */
    $pdURL = $this->makeUrl($this->host, $licTable['Public Domain'][0]);
    $lgplURL = $this->makeUrl($this->host, $licTable['\'LGPL v2.1\'-style'][0]);

    $page = $this->mybrowser->get($pdURL);
    $licFileList = new parseLicFileList($page);
    $tblList = $licFileList->parseLicFileList();
    $tableCnt = count($tblList);
    print "Checking the number of files based on Public Domain\n";
    $this->assertEqual($tableCnt, 4);

    $page = $this->mybrowser->get($lgplURL);
    $licFileList->setPage($page);
    $flist = $licFileList->parseLicFileList();
    print "Checking the number of files based on LGPL v2.1-style\n";
    $flistCnt = count($flist);
    $this->assertEqual($flistCnt, 16);
  }
}
?>
