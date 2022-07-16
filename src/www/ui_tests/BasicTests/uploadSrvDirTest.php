<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
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

  protected function setUp() {
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
}