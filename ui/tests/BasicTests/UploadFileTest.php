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
 * @version "$Id: UploadFileTest.php 4019 2011-03-31 21:23:17Z rrando $"
 *
 * Created on Aug 1, 2008
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class UploadFileTest extends fossologyTestCase
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

    print "starting UploadFileTest\n";

    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Upload/'));
    $this->assertTrue($this->myassertText($loggedIn, '/From File/'));
    $page = $this->mybrowser->get("$URL?mod=upload_file");
    //print "*********** Page after going to upload file **************\n$page\n";
    $this->assertTrue($this->myassertText($page, '/Upload a New File/'));
    $this->assertTrue($this->myassertText($page, '/Select the file to upload:/'));
    $id = $this->getFolderId('Basic-Testing', $page, 'folder');
    $this->assertTrue($this->mybrowser->setField('folder', $id));
    $this->assertTrue($this->mybrowser->setField('getfile', '/home/fosstester/licenses/gpl-3.0.txt' ));
    $desc = 'File gpl-3.0.txt uploaded by test UploadFileTest into Basic-Testing folder';
    $this->assertTrue($this->mybrowser->setField('description', "$desc" ));
    $id = getmypid();
    $upload_name = 'TestUploadFile-' . "$id";
    $this->assertTrue($this->mybrowser->setField('name', $upload_name ));
    /* we won't select any agents this time' */
    $page = $this->mybrowser->clickSubmit('Upload');
    //print "*********** Page after pressing Upload! **************\n$page\n";
    $this->assertTrue($page);
    $this->assertTrue($this->myassertText($page, '/The file .*? has been uploaded/'),
      "FAILURE:Did not find the message 'The file .*? has been uploaded'\n");

  }
}

?>
