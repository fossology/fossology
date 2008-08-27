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
 * @version "$Id$"
 *
 * Created on Aug 1, 2008
 */

require_once ('../../../../tests/fossologyWebTestCase.php');
require_once ('../../../../tests/TestEnvironment.php');

/* every test must use these globals, at least $URL */
global $URL;

class CreateFolderTest extends fossologyWebTestCase
{
  public $folder_name;
  public $mybrowser;

  function testCreateFolder()
  {
    global $URL;

    print "starting CreateFoldertest\n";
    $this->mybrowser = & new SimpleBrowser();
    $page = $this->mybrowser->get($URL);
    $this->assertTrue($page);
    $this->assertTrue(is_object($this->mybrowser));
    $cookie = $this->repoLogin($this->mybrowser);
    $host = $this->getHost($URL);
    $this->mybrowser->setCookie('Login', $cookie, $host);

    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->assertText($loggedIn, '/Organize/'),
                      "FAIL! Could not find Organize menu\n");
    $this->assertTrue($this->assertText($loggedIn, '/Folders /'));
    $this->assertTrue($this->assertText($loggedIn, '/Create/'));
    /* ok, this proves the text is on the page, let's see if we can
     * go to the page and create a folder
     */
    $page = $this->mybrowser->get("$URL?mod=folder_create");
    $this->assertTrue($this->assertText($page, '/Create a new Fossology folder/'));
    /* select the folder to create this folder under */
    $FolderId = $this->getFolderId('Testing', $page);
    $this->assertTrue($this->mybrowser->setField('parentid', $FolderId));
    /* create unique name and insert into form */
    $id = getmypid();
    $this->folder_name = 'TestCreateFolder-' . "$id";

    $this->assertTrue($this->mybrowser->setField('newname', $this->folder_name));
    $desc = 'Folder created by CreateFolderTest as subfolder of Testing';
    $this->assertTrue($this->mybrowser->setField('description', "$desc"));
    $page = $this->mybrowser->clickSubmit('Create!');
    $this->assertTrue(page);
    $this->assertTrue($this->assertText($page, "/Folder $this->folder_name Created/"),
                      "FAIL! Folder $this->folder_name Created not found\n");

    //print "************ page after Folder Create! *************\n$page\n";
  }
  function tearDown()
  {
    global $URL;
    $page = $this->mybrowser->get("$URL?mod=admin_folder_delete");
    $this->assertTrue($this->assertText($page, '/Delete Folder/'));
    $FolderId = $this->getFolderId($this->folder_name, $page);
    $this->assertTrue($this->mybrowser->setField('folder', $FolderId));
    $page = $this->mybrowser->clickSubmit('Delete!');
    $this->assertTrue(page);
    $this->assertTrue($this->assertText($page, "/Deletion of folder $this->folder_name/"),
                      "MoveFoldeTest tearDown FAILED! Deletion of $this->folder_name not found\n");
  }
}
?>
