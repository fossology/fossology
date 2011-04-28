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
 * Verify that the correct menus are visible for the uploader user/perms
 *
 * @version "$Id$"
 *
 * Created on April 28, 2011
 */
require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class ckUploaderTest extends fossologyTestCase
{

  function testUploaderMenus()
  {
    global $URL;
    $this->Login('uploader', '');
    /* we get the home page to get rid of the user logged in page */
    $loggedIn = $this->mybrowser->get("$URL" . 'simpleIndex.php?mod=Default');
    //echo "page after login is:\n$loggedIn\n";
    $this->assertTrue($this->myassertText($loggedIn, '/>Home</'),
      "Fail! Home menu not found");
    $this->assertTrue($this->myassertText($loggedIn, '/>Browse</'),
      "Fail! Browse menu not found");
    $this->assertTrue($this->myassertText($loggedIn, '/>Help</'),
      "Fail! Help menu not found");
    $this->assertTrue($this->myassertText($loggedIn, '/>Organize</'),
      "Fail! Organize menu was NOT found");
    $this->assertTrue($this->myassertText($loggedIn, '/>My Account</'),
      "Fail! My Account menu was not found");
    $this->assertTrue($this->myassertText($loggedIn, '/>Upload</'),
      "Fail! Upload menu was NOT found");
    $this->assertTrue($this->myassertText($loggedIn, '/>Jobs</'),
      "Fail! Jobs menu was NOT found");


    // @todo check that browse shows the users folder (defect in 1.4.0 in that if it's
    // empty, it won't show)... add this in for 1.4.1

    // check that some menus are not present
    $this->assertFalse($this->myassertText($loggedIn, '/>Delete Folder</'),
      "Fail! Delete Folder menu was found, it should not be visible");
    $this->assertFalse($this->myassertText($loggedIn, '/>Delete Uploaded File</'),
      "Fail! Delete Uploaded File menu was found, it should not be visible");
    $page = $this->mybrowser->get("$URL" . 'simpleIndex.php?mod=user_edit_self');
    $this->assertTrue($this->myassertText($page,
      '/You do not have permission to change your default agents/'),
      "Fail! Phrase 'You do not have permission to change your default agents' NOT found");
  }
}
?>
