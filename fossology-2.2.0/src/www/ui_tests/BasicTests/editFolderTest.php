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
 * @version "$Id: editFolderTest.php 2472 2009-08-24 19:35:52Z rrando $"
 *
 * Created on Aug 1, 2008
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

/* every test must use these globals, at least $URL */
global $URL;

class EditFolderTest extends fossologyTestCase
{
  public $editFolderName;
  public $mybrowser;
  public $newname;

  function setUp()
  {
    global $URL;

    $this->Login();
    /* create a subfolder under Basic-Testing, which is edited below */
    $pid = getmypid();
    $this->editFolderName = "EditMe-$pid";
    $this->createFolder('Basic-Testing', $this->editFolderName);
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
    $pid = getmypid();
    $this->newname = "NewEditName-$pid";
    $this->editFolder($this->editFolderName, $this->newname,
                      "Folder name changed to $this->newname by testEditFolder");
    /* check the browse page */
    $page = $this->mybrowser->get("$URL?mod=browse");
    $this->assertTrue($this->myassertText($page, "/$this->newname/"),
                       "editFolderTest FAILED! Folder $this->newname not found\n");
    //print "************ page after check for $this->newname *************\n$page\n";
  }

  function tearDown()
  {
    global $URL;
    $page = $this->mybrowser->get("$URL?mod=admin_folder_delete");
    $this->assertTrue($this->myassertText($page, '/Delete Folder/'));
    $FolderId = $this->getFolderId($this->newname, $page, 'folder');
    $this->assertTrue($this->mybrowser->setField('folder', $FolderId));
    $page = $this->mybrowser->clickSubmit('Delete!');
    $this->assertTrue($page);
    $this->assertTrue($this->myassertText($page, "/Deletion of folder $this->newname/"),
                      "EditFoldeTest tearDown FAILED! Deletion of $this->newname not found\n");
  }
}

?>
