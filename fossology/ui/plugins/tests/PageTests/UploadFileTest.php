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
 * Upload a file using the UI
 *
 *
 *@TODO need to make sure testing folder exists....
 *
 * @version "$Id: $"
 *
 * Created on Aug 1, 2008
 */

 /*
  * Yuk! This test is ugly! May Need A proxy for this test to work
  * inside hp.
  */

require_once ('../../../../tests/fossologyTestCase.php');
require_once ('../../../../tests/TestEnvironment.php');

global $URL;

class UploadFileTest extends fossologyTestCase
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

    print "starting UploadFileTest\n";

    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->assertText($loggedIn, '/Upload/'));
    $this->assertTrue($this->assertText($loggedIn, '/From File/'));

    $page = $this->mybrowser->get("$URL?mod=upload_file");
    $this->assertTrue($this->assertText($page, '/Upload a New File/'));
    $this->assertTrue($this->assertText($page, '/Select the file to upload:/'));
    /* select Testing folder, filename based on pid */

    $id = $this->getFolderId('Testing', $page);
    $this->assertTrue($this->mybrowser->setField('folder', $id));
    $this->assertTrue($this->mybrowser->setField('getfile', '/home/fosstester/licenses/gpl-3.0.txt' ));
    $desc = 'File gpl-3.0.txt uploaded by test UploadFileTest into Testing folder';
    $this->assertTrue($this->mybrowser->setField('description', "$desc" ));
    $id = getmypid();
    $upload_name = 'TestUploadFile-' . "$id";
    $this->assertTrue($this->mybrowser->setField('name', $upload_name ));
    /* we won't select any agents this time' */
    $page = $this->mybrowser->clickSubmit('Upload!');
    $this->assertTrue(page);
    $this->assertTrue($this->assertText($page, '/Upload added to job queue/'));
    //print "*********** Page after upload **************\n$page\n";
  }
}

?>
