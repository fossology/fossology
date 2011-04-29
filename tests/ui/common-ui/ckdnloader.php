<?php
/***********************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
 * Verify that only the home browse my account and help menu's exist for this
 * user
 *
 * @version "$Id$"
 *
 * Created on April 28, 2011
 */
require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class ckdownloaderTest extends fossologyTestCase
{

  function testDownLoaderMenus()
  {
    global $URL;
    
    $this->Login('downloader', '');
    /* we get the home page to get rid of the user logged in page */
    $loggedIn = $this->mybrowser->get("$URL" . 'simpleIndex.php?mod=Default');
    //echo "page after login is:\n$loggedIn\n";
    $this->assertTrue($this->myassertText($loggedIn, '/>Home</'),
      "Fail! Home menu not found");
    $this->assertTrue($this->myassertText($loggedIn, '/>Browse</'),
      "Fail! Browse menu not found");
    $this->assertTrue($this->myassertText($loggedIn, '/>Help</'),
      "Fail! Help menu not found");
    $this->assertTrue($this->myassertText($loggedIn, '/>My Account</'),
      "Fail! My Account menu was not found");

    // Check that browse shows the users folder
    $page = $this->mybrowser->get("$URL" . 'simpleIndex.php?mod=browse');
    $this->assertTrue($this->myassertText($loggedIn, '/>downloader</'),
      "Fail! Folder downloader was NOT found");
    
    // check that some menus are not present
    $this->assertFalse($this->myassertText($loggedIn, '/>Upload</'),
      "Fail! Upload menu found, it should not be visible");
    $this->assertFalse($this->myassertText($loggedIn, '/>Organize</'),
      "Fail! Organize menu found, it should not be visible");
    $this->assertFalse($this->myassertText($loggedIn, '/>Jobs</'),
      "Fail! Jobs menu found, it should not be visible");
  }
}
?>
