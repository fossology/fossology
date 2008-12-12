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
 * Create a folder then delete it.  This is a test to ensure we don't
 * regress in this area.  Use the root folder as the parent folder.
 *
 * @version "$Id$"
 *
 * Created on Dec 12, 2008
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class CreateDeleteFldrTest extends fossologyTestCase
{
  public $folder_name;
  public $mybrowser;

  function setUp()
  {
    global $URL;
    $this->Login();
  }

  function CreateFolder()
  {
    global $URL;

    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Organize/'),
                      "FAIL! Could not find Organize menu\n");
    $this->assertTrue($this->myassertText($loggedIn, '/Folders /'));
    $this->assertTrue($this->myassertText($loggedIn, '/Create/'));
    $page = $this->mybrowser->get("$URL?mod=folder_create");
    $this->assertTrue($this->myassertText($page, '/Create a new Fossology folder/'));
    /* select the folder to create this folder under */
    $this->assertTrue($this->mybrowser->setField('parentid', 1));
    $this->folder_name = 'TestCreateDeleteFolder';

    $this->assertTrue($this->mybrowser->setField('newname', $this->folder_name));
    $desc = 'Folder created by CreateFolderTest as subfolder of Root Folder';
    $this->assertTrue($this->mybrowser->setField('description', "$desc"));
    $page = $this->mybrowser->clickSubmit('Create!');
    $this->assertTrue(page);
    $this->assertTrue($this->myassertText($page, "/Folder $this->folder_name Created/"),
                      "FAIL! Folder $this->folder_name Created not found\nDoes $this->folder_name exist?\n");

    //print "************ page after Folder Create! *************\n$page\n";
  }
  function DeleteFolder()
  {
    global $URL;
    $page = $this->mybrowser->get("$URL?mod=admin_folder_delete");
    $this->assertTrue($this->myassertText($page, '/Delete Folder/'));
    $FolderId = $this->getFolderId($this->folder_name, $page);
    $this->assertTrue($this->mybrowser->setField('folder', $FolderId));
    $page = $this->mybrowser->clickSubmit('Delete!');
    $this->assertTrue(page);
    $this->assertTrue($this->myassertText($page, "/Deletion of folder $this->folder_name/"),
                      "DeleteFolder FAILED! Deletion of $this->folder_name not found\n");
  }

  function TestCreateDeleteFldr()
  {
    print "starting CreateDeleteFolderTest\n";
    // create then remove 10 times....
    for($i=0; $i<10; $i++)
    {
      $this->CreateFolder();
      $this->DeleteFolder();
      sleep(60);            // give delete job some time to remove it...
    }
  }
}
?>
