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

$where = dirname(__FILE__);
if(preg_match('!/home/jenkins.*?tests.*!', $where, $matches))
{
  //echo "running from jenkins....fossology/tests\n";
  require_once ('../../tests/TestEnvironment.php');
  require_once('../../tests/fossologyTestCase.php');
  require_once('../../tests/commonTestFuncs.php');
}
else
{
  //echo "using requires for running outside of jenkins\n";
  require_once ('../../../tests/TestEnvironment.php');
  require_once('../../../tests/fossologyTestCase.php');
  require_once('../../../tests/commonTestFuncs.php');
}

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

    $this->Login();
  }

  function testBrowseUploaded()
  {
    global $URL;

    print "starting BrowseUploadedtest\n";
    $page = $this->mybrowser->get("$URL?mod=browse");
    $this->assertTrue($this->myassertText($page, '/Browse/'),
                      "BrowseUploadedTest FAILED! Could not find Browse menu\n");
    $this->assertTrue($this->myassertText($page, "/Browse/"),
                      "BrowseUploadedTest FAILED! Browse Title not found\n");
    $this->assertTrue($this->myassertText($page, "|simpletest_1\.0\.1\.tar\.gz|"),
                      "BrowseUploadedTest FAILED did not find string simpletest_1.0.1.tar.gz\n");
    $this->assertTrue($this->myassertText($page, "/>View</"),
                      "BrowseUploadedTest FAILED! Do not see  >View< link\n");
    $this->assertTrue($this->myassertText($page, "/>Info</"),
                      "BrowseUploadedTest FAILED!FAIL!Do not see  >Info< not found\n");
    $this->assertTrue($this->myassertText($page, "/>Download</"),
                      "BrowseUploadedTest FAILED!FAIL! Do not see >Download< not found\n");

    // Select 'simpletest_1.0.1.tar.gz' & simpletest_1.0.1.tar links
    $page = $this->mybrowser->clickLink('simpletest_1.0.1.tar.gz');
    //print "*** Page after click simpletest_1.0.1.tar.gz\n$page\n";
    $this->assertTrue($this->myassertText($page, "/simpletest_1\.0\.1\.tar/"),
     "BrowseUploadedTest FAILED! simpletest_1.0.1.tar link not found\n)");
    $page = $this->mybrowser->clickLink('simpletest_1.0.1.tar');
    $this->assertTrue($this->myassertText($page, "/simpletest\//"),
      "BrowseUploadedTest FAILED! simpletest link not found\n)");

    //Select simpltest link
    $page = $this->mybrowser->clickLink('simpletest/');
    $this->assertTrue($this->myassertText($page, "/HELP_MY_TESTS_DONT_WORK_ANYMORE/"));

    /* Select the License link to View License Historgram */
    $page = $this->mybrowser->clickLink('License Browser');
    $this->assertTrue($this->myassertText($page, '/License Browser/'),
      "BrowseUploadedTest FAILED! License Browser not found\n");
    $this->assertTrue($this->myassertText($page, '/3.*?Unique licenses/'),
      "BrowseUploadedTest FAILED! Unique Licenses does not equal 3\n");
    $this->assertTrue($this->myassertText($page, '/123.*?Files/'),
      "BrowseUploadedTest FAILED! Files does not equal 123\n");
  }
}
?>
