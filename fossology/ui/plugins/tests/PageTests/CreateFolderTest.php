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
 * Create a folder under the Testing folder
 *
 * @param
 *
 * @return
 *
 * @version "$Id: $"
 *
 * Created on Aug 1, 2008
 */

require_once ('../../../../tests/fossologyWebTestCase.php');
require_once ('../../../../tests/TestEnvironment.php');

/* every test must use these globals, at least $URL */
global $URL;

class CreateFolderTest extends fossologyWebTestCase
{

  function testCreateFolder()
  {
    global $URL;

    print "starting CreateFoldertest\n";
    $browser = & new SimpleBrowser();
    $page = $browser->get($URL);
    $this->assertTrue($page);
    $this->assertTrue(is_object($browser));
    $cookie = $this->repoLogin($browser);
    $host = $this->getHost($URL);
    $browser->setCookie('Login', $cookie, $host);

    $loggedIn = $browser->get($URL);
    $this->assertTrue($this->assertText($loggedIn, '/Organize/'),
                      "FAIL! Could not find Organize menu\n");
    $this->assertTrue($this->assertText($loggedIn, '/Folders /'));
    $this->assertTrue($this->assertText($loggedIn, '/Create/'));
    /* ok, this proves the text is on the page, let's see if we can
     * go to the page and create a folder
     */
    $page = $browser->get("$URL?mod=folder_create");
    $this->assertTrue($this->assertText($page, '/Create a new Fossology folder/'));
    /* select the folder to create this folder under */
    $FolderId = $this->getFolderId('Testing', $page);
    $this->assertTrue($browser->setField('parentid', $FolderId));
    /* create unique name and insert into form */
    $id = getmypid();
    $folder_name = 'TestCreateFolder-' . "$id";
    $this->assertTrue($browser->setField('newname', $folder_name));
    $desc = 'Folder created by CreateFolderTest as subfolder of Testing';
    $this->assertTrue($browser->setField('description', "$desc"));
    $page = $browser->clickSubmit('Create!');
    $this->assertTrue(page);
    $this->assertTrue($this->assertText($page, "/Folder $folder_name Created/"),
                      "FAIL! Folder $folder_name Created not found\n");

    //print "************ page after Folder Create! *************\n$page\n";
  }
}
?>
