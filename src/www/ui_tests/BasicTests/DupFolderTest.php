<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Try to create a duplicate folder under the root folder
 *

 * @version "$Id: DupFolderTest.php 2292 2009-07-01 18:25:11Z rrando $"
 *
 * Created on Aug 27, 2008
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

/* every test must use these globals, at least $URL */
global $URL;

class DupFolderTest extends fossologyTestCase
{
  public $folder_name;
  public $mybrowser;

  function setUP()
  {
    global $URL;

    $this->Login();
  }

  function testCreateFolder()
  {
    global $URL;

    print "starting DupFoldertest\n";
    /* try to create the same folder twice */
    for ($i = 0; $i < 2; $i++)
    {
      $this->mybrowser->get($URL);
      $page = $this->mybrowser->clickLink('Create');
      $this->assertTrue($this->myassertText($page, '/Create a new Fossology folder/'));
      /* select the folder to create this folder under */
      $this->assertTrue($this->mybrowser->setField('parentid', 1));
      /* create unique name and insert into form */
      $id = getmypid();
      $this->folder_name = 'TestCreateFolder-' . "$id";
      $this->assertTrue($this->mybrowser->setField('newname', $this->folder_name));
      $desc = 'Folder created by DupFolderTest as subfolder of RootFolder';
      $this->assertTrue($this->mybrowser->setField('description', "$desc"));
      $page = $this->mybrowser->clickSubmit('Create!');
      $this->assertTrue($page);
      /* On the second try, we SHOULD NOT see Folder xxx Created */
      if($i == 1)
      {
        $this->assertFalse($this->myassertText($page, "/Folder $this->folder_name Created/"),
              "FAIL! Folder $this->folder_name Created Was seen,\n");
      }
      else
      {
        $this->assertTrue($this->myassertText($page, "/Folder $this->folder_name Created/"),
                "FAIL! Folder $this->folder_name Created not found\n");
      }
      //print "************ page after Folder Create! *************\n$page\n";
    }
  }

  function tearDown()
  {
    global $URL;
    $page = $this->mybrowser->get("$URL?mod=admin_folder_delete");
    $this->assertTrue($this->myassertText($page, '/Delete Folder/'));
    $FolderId = $this->getFolderId($this->folder_name, $page, 'folder');
    $this->assertTrue($this->mybrowser->setField('folder', $FolderId));
    $page = $this->mybrowser->clickSubmit('Delete!');
    $this->assertTrue($page);
    $this->assertTrue($this->myassertText($page, "/Deletion of folder $this->folder_name/"),
     "DupFoldeTest tearDown FAILED! Deletion of $this->folder_name not found\n");
  }
}
