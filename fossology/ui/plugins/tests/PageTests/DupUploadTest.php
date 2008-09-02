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
 * Duplicate upload test, try to upload the same file twice into the
 * same folder.
 *
 *
 *@TODO need to make sure testing folder exists....
 *
 * @version "$Id: $"
 *
 * Created on Aug 28, 2008
 */

require_once ('../../../../tests/fossologyTestCase.php');
require_once ('../../../../tests/TestEnvironment.php');

global $URL;

class DupUploadTest extends fossologyTestCase
{
  public $mybrowser;

  function setUP()
  {
    global $URL;

    $this->mybrowser = & new SimpleBrowser();
    $page = $this->mybrowser->get($URL);
    $this->assertTrue($page);
    $this->assertTrue(is_object($this->mybrowser));
    $cookie = $this->repoLogin($this->mybrowser);
    $host = $this->getHost($URL);
    $this->mybrowser->setCookie('Login', $cookie, $host);
  }

  function testUploadFile()
  {
    global $URL;

    print "starting DupUploadTest\n";

    for ($i = 0; $i < 2; $i++)
    {
      $loggedIn = $this->mybrowser->get($URL);
      $this->assertTrue($this->assertText($loggedIn, '/Upload/'));
      $this->assertTrue($this->assertText($loggedIn, '/From File/'));

      $page = $this->mybrowser->clickLink('From File');
      $this->assertTrue($this->assertText($page, '/Upload a New File/'));
      $this->assertTrue($this->assertText($page, '/Select the file to upload:/'));
      /* select Testing folder, filename based on pid */

      $id = $this->getFolderId('Testing', $page);
      $this->assertTrue($this->mybrowser->setField('folder', $id));
      $this->assertTrue($this->mybrowser->setField('getfile', '/home/fosstester/licenses/Affero-v1.0'));
      $desc = 'File Affero-v1.0 uploaded by test UploadFileTest into Testing folder';
      $this->assertTrue($this->mybrowser->setField('description', "$desc"));
      $id = getmypid();
      $upload_name = 'TestUploadFile-' . "$id";
      $this->assertTrue($this->mybrowser->setField('name', $upload_name));
      /* we won't select any agents this time' */
      $page = $this->mybrowser->clickSubmit('Upload!');
      $this->assertTrue(page);
            /* On the second try, we SHOULD NOT see Upload added to job queue */
      if($i == 1)
      {
        $this->assertFalse($this->assertText($page, "/Upload added to job queue/"),
              "FAIL! Duplicate Upload created!\nUpload added to job queue Was seen,\n");
      }
      else
      {
        $this->assertTrue($this->assertText($page, "/Upload added to job queue/"),
                "FAIL! Upload Failed?\nUpload added to job queue not found\n");
      }
      //print "*********** Page after upload **************\n$page\n";
    }
  }
}
?>
