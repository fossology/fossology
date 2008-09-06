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
 * Basic edit folder test
 *
 * @param
 *
 * @return
 *
 * @version "$Id$"
 *
 * Created on Aug 1, 2008
 */

require_once ('../../../../tests/fossologyTestCase.php');
require_once ('../../../../tests/TestEnvironment.php');

/* every test must use these globals, at least $URL */
global $URL;

class EditFolderTest extends fossologyTestCase
{
  public $folder_name;
  public $mybrowser;
  public $newname;

  function setUp()
  {
    global $URL;

    $this->Login($this->mybrowser);
    /* create a folder, which is edited below */
    $page = $this->mybrowser->get("$URL?mod=folder_create");
    $this->assertTrue($this->myassertText($page, '/Create a new Fossology folder/'));
    /* select the folder to create this folder under */
    $FolderId = $this->getFolderId('Testing', $page);
    $this->assertTrue($this->mybrowser->setField('parentid', $FolderId));
    $this->folder_name = 'EditMe';
    $this->assertTrue($this->mybrowser->setField('newname', $this->folder_name));
    $desc = 'Folder created by EditFolderTest as subfolder of Testing';
    $this->assertTrue($this->mybrowser->setField('description', "$desc"));
    $page = $this->mybrowser->clickSubmit('Create!');
    $this->assertTrue(page);
    $this->assertTrue($this->myassertText($page, "/Folder $this->folder_name Created/"),
                      "FAIL! Folder $this->folder_name Created not found\n");
  }

  function testEditFolder()
  {
    global $URL;

    print "starting EditFoldertest\n";

    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Organize/'),
                      "FAIL! Could not find Organize menu\n");
    $this->assertTrue($this->myassertText($loggedIn, '/Folders /'));
    $this->assertTrue($this->myassertText($loggedIn, '/Edit Properties/'));
    /* ok, this proves the text is on the page, let's see if we can
     * go to the page and delete a folder
     */
    $page = $this->mybrowser->get("$URL?mod=folder_properties");
    $this->assertTrue($this->myassertText($page, '/Edit Folder Properties/'));
    $FolderId = $this->getFolderId('EditMe', $page);
    $this->assertTrue($FolderId);
    $this->assertTrue($this->mybrowser->setField('oldfolderid', $FolderId));
    /* edit the properties */
    $pid = getmypid();
    $this->newname = "FolderEditedByTest-$pid";
    $this->assertTrue($this->mybrowser->setField('newname', "$this->newname"));
    $desc = 'Folder name/description changed by EditFolderTest as subfolder of Testing';
    $this->assertTrue($this->mybrowser->setField('newdesc', "$desc"),
                      "FAIL! Could not set description 'newdesc'\n");
    $page = $this->mybrowser->clickSubmit('Edit!');
    $this->assertTrue(page);
    $this->assertTrue($this->myassertText($page, "/Folder Properties changed/"),
                      "FAIL! Folder Properties changed not found\n");
    /* check the browse page */
    $page = $this->mybrowser->get("$URL?mod=browse");
    $this->assertTrue($this->myassertText($page, "/$this->newname/"),
                       "FAIL! Folder $this->newname not found\n");
    //print "************ page after Folder Delete! *************\n$page\n";
  }
  function tearDown()
  {
    global $URL;
    $page = $this->mybrowser->get("$URL?mod=admin_folder_delete");
    $this->assertTrue($this->myassertText($page, '/Delete Folder/'));
    $FolderId = $this->getFolderId($this->newname, $page);
    $this->assertTrue($this->mybrowser->setField('folder', $FolderId));
    $page = $this->mybrowser->clickSubmit('Delete!');
    $this->assertTrue(page);
    $this->assertTrue($this->myassertText($page, "/Deletion of folder $this->newname/"),
                      "EditFoldeTest tearDown FAILED! Deletion of $this->newname not found\n");
  }
}

?>
