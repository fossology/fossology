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
 * Browse an uploaded file test
 *
 * @version "$Id$"
 *
 * Created on Aug 13, 2008
 */

require_once ('../../../../tests/fossologyTestCase.php');
require_once ('../../../../tests/TestEnvironment.php');

global $URL;

class browseUPloadedTest extends fossologyTestCase
{
  public $mybrowser;
  public $host;

  function setUp()
  {
    /*
     * This test needs to have file(s) uploaded to browse.  The issue is
     * that uploads can take an indeterminate amount of time.  These
     * jobs should be started before the tests are run?  This is an
     * ongoing issue for testing this product.
     *
     * For now, the setup will just verify the material is there?
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
  }

  function testBrowseUploaded()
  {
    global $URL;

    $name = 'simpletest_1\.0\.1\.tar\.gz';

    print "starting BrowseUploadedtest\n";
    $page = $this->mybrowser->get("$URL?mod=browse");
    $this->assertTrue($this->myassertText($page, '/Browse/'),
                      "FAIL! Could not find Browse menu\n");

    /* select simpltest upload */
    $link = $this->getNextLink("/href='((.*?)&show=detail).*?$name/",$page);
    $upLink = $this->makeUrl($this->host, $link);
    $page = $this->mybrowser->get($upLink);
    //print "************ Page after upload link *************\n$page\n";
    $this->assertTrue($this->myassertText($page, "/Browse/"),
                      "FAIL! Browse Title not found\n");
    $this->assertTrue($this->myassertText($page, "/$name/"),
                      "FAIL! did not find simpletest_1.0.1.tar.gz\n");
    $this->assertTrue($this->myassertText($page, "/>View</"),
                      "FAIL! >View< not found\n");
    $this->assertTrue($this->myassertText($page, "/>Meta</"),
                      "FAIL! >Meta< not found\n");
    $this->assertTrue($this->myassertText($page, "/>Download</"),
                      "FAIL! >Download< not found\n");

    /* Select 'simpletest_1.0.1.tar.gz' */
    $link = $this->getNextLink("/ class=.*?href='(.*?)'>$name/", $page);
    $compressedLink = $this->makeUrl($this->host, $link);
    $page = $this->mybrowser->get($compressedLink);
    $this->assertTrue($this->myassertText($page, "/simpletest\//"));
    $this->assertFalse($this->myassertText($page, "/>View</"),
                      "FAIL! >View< was found\n");
    $this->assertFalse($this->myassertText($page, "/>Meta</"),
                      "FAIL! >Meta< was found\n");
    $this->assertFalse($this->myassertText($page, "/>Download</"),
                      "FAIL! >Download< was found\n");

    /* Select simpltest link */
    $name = 'simpletest';
    $link = $this->getNextLink("/ class=.*?href='(.*?)'>$name/", $page);
    $simpleLink = $this->makeUrl($this->host, $link);
    $page = $this->mybrowser->get($simpleLink);
    $this->assertTrue($this->myassertText($page, "/HELP_MY_TESTS_DONT_WORK_ANYMORE/"));
    $this->assertTrue($this->myassertText($page, "/$name/"),
                      "FAIL! did not find simpletest_1.0.1.tar.gz\n");
    $this->assertTrue($this->myassertText($page, "/>View</"),
                      "FAIL! >View< not found\n");
    $this->assertTrue($this->myassertText($page, "/>Meta</"),
                      "FAIL! >Meta< not found\n");
    $this->assertTrue($this->myassertText($page, "/>Download</"),
                      "FAIL! >Download< not found\n");

    /* Select the License link to View License Historgram */
    $link = $this->getNextLink("/href='((.*?mod=license).*?)'.*?License</", $page);
    $tblLink = $this->makeUrl($this->host, $link);
    $page = $this->mybrowser->get($tblLink);
    $this->assertTrue($this->myassertText($page, '/License Browser/'),
                      "FAIL! License Browser not found\n");
    $this->assertTrue($this->myassertText($page, '/Total licenses: 3/'),
                      "FAIL! Total Licenses does not equal 3\n");
    //print "************ Should be a License Browser page *************\n$page\n";
    /* Select Show in the table */
    $showLink = $this->getNextLink("/href='((.*?mod=search_file_by_license).*?)'.*?Show/", $page);
    /* view the license */
    $licLink = $this->getNextLink("/href='((.*?mod=view-license).*?)'.*?LICENSE</", $page);
    $viewLink = $this->makeUrl($this->host, $licLink);
    $page = $this->mybrowser->get($viewLink);
    $this->assertTrue($this->myassertText($page, '/View License/'),
                          "FAIL! View License not found\n");
    $licenseResult = $this->mybrowser->getContentAsText($viewLink);
    $this->assertTrue($this->myassertText($licenseResult, '/100% view LGPL v2\.1/'),
                      "FAIL! Did not find '100% view LGPL v2.1'\n   In the License Table for simpletest\n");

    //print "************ page after Browse $nlink *************\n$page\n";
  }
}
?>
