<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * uploadSrvFileTest
 *
 * Upload a File
 *
 * @version "$Id: uploadSrvFileTest.php 2472 2009-08-24 19:35:52Z rrando $"
 *
 * Created on April 7, 2009
 */
require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class uploadSrvFileTest extends fossologyTestCase {

  public $mybrowser;

  protected function setUp() {
    $this->Login();
    $this->CreateFolder(1, 'SrvUploads', 'Folder for upload from server tests');
  }

  public function testUploadSrvFile() {

    global $URL;

    $page = $this->mybrowser->get($URL);

    $File = '/home/fosstester/licenses/ApacheLicense-v2.0';
    $Filedescription = "File uploaded from Server";

    $this->uploadServer('SrvUploads', $File, $Filedescription, null, 'all');
  }
}