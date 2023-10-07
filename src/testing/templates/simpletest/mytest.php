<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Template to use for a simpletest test
 *
 * @version "$Id: mytest.php 2017 2009-04-25 03:02:01Z rrando $"
 *
 * Created on Aug 1, 2008
 */

/* every test must use these includes, adjust the paths based on where the
 * tests are in the source tree.
 */
require_once ('TestEnvironment.php');
require_once ('fossologyTestCase.php');

global $URL;

class myFirstTest extends fossologyTestCase
{
  public $mybrowser;
  public $testFolder;

  /*
   * Every Test needs to login so we use the setUp method for that.
   * setUp is called before any other method by default.
   *
   * If other actions like creating a folder or something are needed,
   * put them in the setUp method after login.
   *
   */
  function setUp()
  {
    $this->Login();
  }
/* all runnable test names (methods/functions) must start with 'test' */
  function testmytest()
  {
    global $URL;
    print "starting testmytest\n";
    $page = $this->mybrowser->get($URL);
    $page = $this->mybrowser->clickLink('Browse');
    //print "page after Browse is:\n$page\n";
    $this->assertTrue($this->myassertText($page,'/Folder Navigation/'),
                      "testmyTest FAILED! There is no Folder Navigation Title\n");
    $page = $this->mybrowser->clickLink('Create');
    $this->testFolder = 'Sample-Folder';
    $this->createFolder('Testing', $this->testFolder, null);
    /* normally one should verify that the folder was created.  You could
     * see if it was in the Software Repository listing, you could find
     * it's folder_pk in the page and verify that by looking in the
     * db... for this sample, the tear down method will also serve as a
     * verify method.  If teardown fails, because it can't find the
     * folder, then we know that the folder create failed.  Additionally
     * the createFolder routine verifies it saw the folder created
     * message... so for this example, I skipped it.
     */
  }
  /* use the tearDown method to clean up after a test.  This method like
   * setUp will run after every test.
   */

   function tearDown()
   {
    global $URL;
    print "mytest: in tearDown\n";
    $page = $this->mybrowser->get("$URL?mod=admin_folder_delete");
    $this->assertTrue($this->myassertText($page, '/Delete Folder/'));
    $FolderId = $this->getFolderId($this->testFolder, $page);
    $this->assertTrue($this->mybrowser->setField('folder', $FolderId));
    $page = $this->mybrowser->clickSubmit('Delete!');
    $this->assertTrue(page);
    $this->assertTrue($this->myassertText($page, "/Deletion of folder $this->testFolder/"),
                      "FolderTest tearDown FAILED! Deletion of $this->testFolder not found\n");
   }
}
