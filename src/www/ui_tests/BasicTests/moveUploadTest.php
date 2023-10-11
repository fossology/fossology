<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * Move Upload test
 *
 * Move an upload from root folder to Testing folder.
 *
 * @version "$Id: moveUploadTest.php 2472 2009-08-24 19:35:52Z rrando $"
 *
 * Created on Aug 1, 2008
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;
/**
 * MoveFolderTest
 *
 * simple class to test the standard case of moving an upload.  Since
 * all selections are made via pull downs, there is no opportunity to
 * inject folders or uploads that don't exit.
 *
 * This test depends on
 *
 */
class MoveFolderTest extends fossologyTestCase
{
  public $upload2Move;
  public $fromFolder='root';
  public $toFolder;
  public $mybrowser;

  function setUp()
  {
    //print "starting setUp moveUploadTest\n";
    $this->Login();
  }
  /**
   * testMoveUpload
   *
   * Generic move test, move an upload from root folder to Testing
   * folder.
   *
   */
  function testMoveUpload()
  {
    global $URL;

    print "starting MoveUploadtest\n";
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Organize/'),
                      "FAIL! Could not find Organize menu\n");
    $this->assertTrue($this->myassertText($loggedIn, '/Uploads /'),
                "testMoveUpload FAILED! coun not find Uploads menu\n");
    /* this assertion below is bogus, there are multiple Move (s) */
    $this->assertTrue($this->myassertText($loggedIn, '/Move/'));
    $this->upload2Move = 'gpl-3.0.txt';
    $this->toFolder    = 'Basic-Testing';
    /* fromFolder is 'root' */
    $this->moveUpload($this->fromFolder, $this->toFolder, $this->upload2Move);
    /* best we can do with simpletest is to see if the folder is still there.
     *
     */
    $page = $this->mybrowser->clickLink('Browse');
    $page = $this->mybrowser->clickLink('Basic-Testing');
    $this->assertTrue($this->myassertText($page, "/$this->upload2Move/"),
                       "moveUploadTest FAILED! Folder $this->upload2Move does not exist under Basic-Testing folder\n");
    //print "************ page after Move! *************\n$page\n";
  }

  /*
   * move the upload back to root folder so the test can be run
   * multiple times.
   */
  function tearDown()
  {
    $this->moveUpload('Basic-Testing', 'root', $this->upload2Move);
  }
}
