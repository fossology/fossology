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
 * Move folder test
 *
 * @param
 *
 * @return
 *
 * @version "$Id$"
 *
 * Created on Aug 1, 2008
 */

/* every test must use these includes, adjust the paths based on where the
 * tests are in the source tree.
 */
require_once ('../../../../tests/fossologyWebTestCase.php');
require_once ('../../../../tests/TestEnvironment.php');

/* every test must use these globals, at least $URL */
global $URL;

/* The class name should end in Test */

class MoveFolderTest extends fossologyWebTestCase
{
  public $folder_name;
  public $move_folder;
  public $mybrowser;

  function setUp()
  {
    global $URL;

    //print "starting setUp DeleteFoldertest\n";
    $browser = & new SimpleBrowser();
    $page = $browser->get($URL);
    $this->assertTrue($page);
    $this->assertTrue(is_object($browser));
    $this->mybrowser = $browser;
    $cookie = $this->repoLogin($this->mybrowser);
    $host = $this->getHost($URL);
    $this->mybrowser->setCookie('Login', $cookie, $host);
    /* create a folder to move the folder to */
    $page = $this->mybrowser->get("$URL?mod=folder_create");
    $this->assertTrue($this->assertText($page, '/Create a new Fossology folder/'));
    /* select the folder to create this folder under */
    $FolderId = $this->getFolderId('Testing', $page);
    $this->assertTrue($this->mybrowser->setField('parentid', $FolderId));
    $pid = getmypid();
    $this->move_folder = "MoveTest-$pid";
    $this->assertTrue($this->mybrowser->setField('newname', $this->move_folder));
    $desc = 'Folder created by MoveFolderTest as subfolder of Testing';
    $this->assertTrue($this->mybrowser->setField('description', "$desc"));
    $page = $this->mybrowser->clickSubmit('Create!');
    $this->assertTrue(page);
    $this->assertTrue($this->assertText($page, "/Folder $this->move_folder Created/"),
                      "FAIL! Folder $this->move_folder Created not found\n");
    /* create a folder, which get's moved below */
    $page = $this->mybrowser->get("$URL?mod=folder_create");
    $this->assertTrue($this->assertText($page, '/Create a new Fossology folder/'));
    /* select the folder to create this folder under */
    $FolderId = $this->getFolderId($this->move_folder, $page);
    $this->assertTrue($this->mybrowser->setField('parentid', $FolderId));
    $pid = getmypid();
    $this->folder_name = "MoveMe-$pid";
    $this->assertTrue($this->mybrowser->setField('newname', $this->folder_name));
    $desc = 'Folder created by MoveFolderTest as subfolder of Testing';
    $this->assertTrue($this->mybrowser->setField('description', "$desc"));
    $page = $this->mybrowser->clickSubmit('Create!');
    $this->assertTrue(page);
    $this->assertTrue($this->assertText($page, "/Folder $this->folder_name Created/"),
                      "FAIL! Folder $this->folder_name Created not found\n");
  }

  function testMoveFolder()
  {
    global $URL;

    print "starting MoveFoldertest\n";
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->assertText($loggedIn, '/Organize/'),
                      "FAIL! Could not find Organize menu\n");
    $this->assertTrue($this->assertText($loggedIn, '/Folders /'));
    $this->assertTrue($this->assertText($loggedIn, '/Move/'));
    /* ok, this proves the text is on the page, let's see if we can
     * go to the page and delete a folder
     */
    $page = $this->mybrowser->get("$URL?mod=folder_move");
    $this->assertTrue($this->assertText($page, '/Move Folder/'));
    $FolderId = $this->getFolderId($this->folder_name, $page);
    $this->assertTrue($this->mybrowser->setField('oldfolderid', $FolderId));
    $MvFolderId = $this->getFolderId($this->move_folder, $page);
    $this->assertTrue($this->mybrowser->setField('targetfolderid', $MvFolderId));
    $page = $this->mybrowser->clickSubmit('Move!');
    $this->assertTrue(page);
    //print "************ page after Folder Move! *************\n$page\n";
    $this->assertTrue($this->assertText($page, "/Moved folder $this->folder_name to folder/"),
                      "FAIL! Moved folder $this->folder_name to folder not found\n");
    $page = $this->mybrowser->get("$URL?mod=browse");
    /* best we can do with simpletest is to see if the folder is still there.
     * This is a place where selenium may be useful.
     */
    $this->assertTrue($this->assertText($page, "/$this->folder_name/"),
                       "FAIL! Folder $this->folder_name no longer exists!\n");
    //print "************ page after Folder Delete! *************\n$page\n";
  }

  /* remove the test folders created above  Only need to remove the top
   * move folder, that should remove any subfolders in it.
   */
  function tearDown()
  {
    $page = $this->mybrowser->get("$URL?mod=admin_folder_delete");
    $this->assertTrue($this->assertText($page, '/Delete Folder/'));
    $FolderId = $this->getFolderId($this->move_folder, $page);
    $this->assertTrue($this->mybrowser->setField('folder', $FolderId));
    $page = $this->mybrowser->clickSubmit('Delete!');
    $this->assertTrue(page);
    $this->assertTrue($this->assertText($page, "/Deletion of folder $this->move_folder/"),
                      "MoveFoldeTest tearDown FAILED! Deletion of $this->move_folder not found\n");
  }
}
?>
