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
 * uploadSrvDirTest
 *
 * Upload a directory of information.  It should get tar'ed up and uploaded.
 *
 * @version "$Id: uploadSrvDirTest.php 1977 2009-04-08 04:01:03Z rrando $"
 *
 * Created on April 7, 2009
 */
require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class uploadSrvDirTest extends fossologyTestCase {

  public $mybrowser;

  public function setUp() {
    global $URL;
    $this->Login();
    $this->CreateFolder(1, 'SrvUploads', 'Folder for upload from server tests');
  }

  public function testUploadSrvDir() {

    global $URL;

    $page = $this->mybrowser->get($URL);

    $Dir = '/home/fosstester/licenses/Tdir';
    $Dirdescription = "Directory of licenses";

    $this->uploadServer('SrvUploads', $Dir, $Dirdescription, null, 'all');
  }
};
?>