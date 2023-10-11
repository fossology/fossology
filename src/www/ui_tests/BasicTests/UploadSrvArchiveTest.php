<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Upload a file from the server using the UI
 *
 *@TODO need to make sure testing folder exists....
 *@TODO needs setup and account to really work well...
 *
 * @version "$Id: UploadSrvArchiveTest.php 2472 2009-08-24 19:35:52Z rrando $"
 *
 * Created on Aug 1, 2008
 */

/*
 * NOTE this test is difficult in that the material uploaded MUST be
 * available to the test.  If multiple agent systems are used, then the
 * material must be available there as well.
 *
 * One possibility is to modify the readme to include the creation of
 * a test user and material.  Since it takes sudo, the test cannot
 * automatically do it. Well it could, but it's a bad idea.
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class UploadSrvTest extends fossologyTestCase {
  function setUp() {
    /* check to see if the user and material exist*/
    $this->assertTrue(file_exists('/home/fosstester/.bashrc'),
                      "FAILURE! .bashrc not found\n");
    $this->Login();
  }

  function testUploadUSrv() {

    global $URL;

    print "starting UploadUSrvTest\n";
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Upload/'),
                      'Did not find Upload Menu');
    $this->assertTrue($this->myassertText($loggedIn, '/From Server/'),
                      'Did not find From Server Menu');
    $page = $this->mybrowser->get("$URL?mod=upload_srv_files");
    $this->assertTrue($this->myassertText($page, '/Upload from Server/'),
                      'Did not find Upload from Server Title');
    $this->assertTrue($this->myassertText($page, '/on the server to upload:/'),
                      'Did not find the sourcefile Selection Text');
    /* select Testing folder */
    $FolderId = $this->getFolderId('Basic-Testing', $page, 'folder');
    $this->assertTrue($this->mybrowser->setField('folder', $FolderId));
    $this->assertTrue($this->mybrowser->setField('sourcefiles', '/home/fosstester/archives/simpletest_1.0.1.tar.gz'));
    $desc = 'File uploaded by test UploadSrvTest to folder Testing';
    $this->assertTrue($this->mybrowser->setField('description', "$desc"));
    /* we won't select any agents this time' */
    $page = $this->mybrowser->clickSubmit('Upload!');
    $this->assertTrue($page);
    $this->assertTrue($this->myassertText($page,
                     '/The upload for \/home\/fosstester\/archives\/simpletest_1\.0\.1\.tar\.gz/'),
                      "FAIL! Did not match Upload message\n");
    //print "************ page after Upload! *************\n$page\n";
  }
}
