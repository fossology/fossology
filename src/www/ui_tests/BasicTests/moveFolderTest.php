<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * Move folder test
 *
 * Move a folder from Testing folder to another folder.
 *
 * @version "$Id: moveFolderTest.php 2472 2009-08-24 19:35:52Z rrando $"
 *
 * Created on Aug 1, 2008
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class MoveFolderTest extends fossologyTestCase
{
  public $folder2Move;
  public $moveFolder;
  public $mybrowser;

  function setUp()
  {
    global $URL;

    print "starting setUp Foldertest\n";
    $this->Login();
    /*
     * Create the MoveTest folder as a subfolder of the Testing folder
     * first go to the move page so the folder id's can be found.
     */
    $this->mybrowser->get($URL);
    $page = $this->mybrowser->clickLink('Move');
    $FolderId = $this->getFolderId('Basic-Testing', $page, 'oldfolderid');
    if(empty($FolderId))
    {
      $this->fail("MoveFolderTest will fail, no Testing folder to use.\n Please correct and rerun\n");
    }
    $pid = getmypid();
    $this->moveFolder = "MoveTest-$pid";
    $this->createFolder('Basic-Testing', $this->moveFolder);

    /* create a folder, which get's moved below */
    $pid = getmypid();
    $this->folder2Move = "MoveMe-$pid";
    $this->createFolder('Basic-Testing', $this->folder2Move);

  }
  /**
   * testMoveFolder
   *
   * Generic move test, move a folder from Testing folder to another
   * subfolder under the Testing folder.
   *
   */
  function testMoveFolder()
  {
    global $URL;

    print "starting MoveFoldertest\n";
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Organize/'),
                      "FAIL! Could not find Organize menu\n");
    $this->assertTrue($this->myassertText($loggedIn, '/Folders /'));
    $this->assertTrue($this->myassertText($loggedIn, '/Move/'));
    $this->mvFolder($this->folder2Move, $this->moveFolder);
    /* best we can do with simpletest is to see if the folder is still there.
     * This is a place where selenium may be useful.
     */
    $page = $this->mybrowser->clickLink('Browse');
    $this->assertTrue($this->myassertText($page, "/$this->folder2Move/"),
                       "MoveFolderTest FAILED! Folder $this->folder2Move no longer exists!\n");
    //print "************ page after Move! *************\n$page\n";
  }

  /* remove the test folders created above  Only need to remove the top
   * move folder, that should remove any subfolders in it.
   */
  function tearDown()
  {

    global $URL;

    $page = $this->mybrowser->get("$URL?mod=admin_folder_delete");
    $this->assertTrue($this->myassertText($page, '/Delete Folder/'));
    $FolderId = $this->getFolderId($this->moveFolder, $page, 'folder');
    $this->assertTrue($this->mybrowser->setField('folder', $FolderId));
    $page = $this->mybrowser->clickSubmit('Delete!');
    $this->assertTrue($page);
    $this->assertTrue($this->myassertText($page, "/Deletion of folder $this->moveFolder/"),
                      "MoveFoldeTest tearDown FAILED! Phrase 'Deletion of $this->moveFolder' not found\n");
  }
}
