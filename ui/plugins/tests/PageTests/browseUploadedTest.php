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
 * @version "$Id: $"
 *
 * Created on Aug 13, 2008
 */

require_once ('../../../../tests/fossologyWebTestCase.php');
require_once ('../../../../tests/TestEnvironment.php');

global $URL;

class browseUPloadedTest extends fossologyWebTestCase
{
  public $mybrowser;

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
    $host = $this->getHost($URL);
    $this->mybrowser->setCookie('Login', $cookie, $host);
  }

  function testBrowseUploaded()
  {
    global $URL;

    $upload_name = 'simpletest_1\.0\.1\.tar\.gz';

    print "starting BrowseUploadedtest\n";
    $loggedIn = $this->mybrowser->get("$URL?mod=browse");
    $this->assertTrue($this->assertText($loggedIn, '/Browse/'),
                      "FAIL! Could not find Browse menu\n");
    $stuff = $this->getBrowseUri($upload_name, $loggedIn);
    print "************ Suff after getBrowseUri *************\n$stuff\n";

    //$this->assertTrue($this->assertText($page, "/Moved folder $this->folder_name to folder/"),
      //                "FAIL! Moved folder $this->folder_name to folder not found\n");
    //print "************ page after Folder Move! *************\n$page\n";
  }
}
?>
