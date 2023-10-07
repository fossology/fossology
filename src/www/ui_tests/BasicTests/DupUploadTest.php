<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Duplicate upload test, try to upload the same file twice into the
 * same folder.  This should pass.  It's allowed.
 *
 *
 * @version "$Id: DupUploadTest.php 2472 2009-08-24 19:35:52Z rrando $"
 *
 * Created on Aug 28, 2008
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class DupUploadTest extends fossologyTestCase
{
  public $mybrowser;

  function setUP()
  {
    global $URL;
    $this->Login();
  }

  function testUploadFile()
  {
    global $URL;

    print "starting DupUploadTest\n";

    for ($i = 0; $i < 2; $i++)
    {
      $loggedIn = $this->mybrowser->get($URL);
      $this->assertTrue($this->myassertText($loggedIn, '/Upload/'));
      $this->assertTrue($this->myassertText($loggedIn, '/From File/'));

      $page = $this->mybrowser->clickLink('From File');
      $this->assertTrue($this->myassertText($page, '/Upload a New File/'));
      $this->assertTrue($this->myassertText($page, '/Select the file to upload:/'));
      /* select Testing folder, filename based on pid */

      $id = $this->getFolderId('Basic-Testing', $page, 'folder');
      $this->assertTrue($this->mybrowser->setField('folder', $id));
      $this->assertTrue($this->mybrowser->setField('getfile', '/home/fosstester/licenses/Affero-v1.0'));
      $desc = 'File Affero-v1.0 uploaded by test UploadFileTest into Testing folder';
      $this->assertTrue($this->mybrowser->setField('description', "$desc"));
      $id = getmypid();
      $upload_name = 'TestUploadFile-' . "$id";
      $this->assertTrue($this->mybrowser->setField('name', $upload_name));
      /* we won't select any agents this time' */
      $page = $this->mybrowser->clickSubmit('Upload');
      $this->assertTrue($page);
            /* On the second try, we SHOULD see Upload added to job queue */
      if($i == 1) {
        $this->assertTrue($this->myassertText($page, "/The file $upload_name has been uploaded/"),
              "FAIL! A Duplicate Upload was NOT created!\n" .
              "The phrase, The file $upload_name has been uploaded was NOT seen\n");
      }
      else{
        $this->assertFalse($this->myassertText($page, "/Upload failed/"),
                "FAIL! Upload Failed?\nPhrase 'Upload failed found\n");
      }
      //print "*********** Page after upload **************\n$page\n";
    }
  }
}
