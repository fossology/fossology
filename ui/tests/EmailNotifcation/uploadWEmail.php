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
 * uploadWEmail
 *
 * Upload files as a user with email notification
 *
 * Note: you must have at least local email delivery working on the system
 * that this test is run on.
 *
 * @version "$Id$"
 *
 * Created on April 3, 2009
 */
require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class uploadWEMailTest extends fossologyTestCase {

  public $mybrowser;

  public function setUp() {
    global $URL;

    $this->Login();
    $this->CreateFolder(1, 'Enote', 'Folder for Email notification uploads');
    $this->Logout();
  }

  public function testUploadWEmail() {

    global $URL;

    print "Starting UploadWEmail test...\n";
    /* login fosstester*/
    $this->Login('fosstester','fosstester');
    $page = $this->mybrowser->get($URL);

    $File = '/home/fosstester/licenses/Apache-v1.1';
    $Filedescription = "The Apache License Version 1.1 from the Apache site";

    $Url = 'http://www.apache.org/licenses/LICENSE-2.0';
    $Urldescription = "The Apache License Version 2.0 from www.apache.org/licenses/LICENSE-2.0";

    $Srv = '/home/fosstester/archives/foss23D1F1L.tar.bz2';
    $Srvdescription = "fossology test archive";

    $this->uploadFile('Enote', $File, $Filedescription, null, '1');
    $this->uploadUrl('Enote', $Url, $Urldescription, null, '2');
    $this->uploadServer('Enote', $Srv, $Srvdescription, null, 'all');
    // need to get the upload id's of the files just uploaded.
    sleep(10);   // wait for 2 min for jobs to start then check they got started
    // use fossjobs to get the upload id
    $jobs = $this->parseFossjobs();
    //print "returned jobs from fossjobs is:\n";print_r($jobs) . "\n";

    /* verify */
    print "Verifying jobs exist\n";
    if(array_key_exists($Srv,$jobs)) {
      $this->pass();
    }
    else {
      $this->fail("upload $Srv not found\n");
    }
    if(array_key_exists($Url,$jobs)) {
      $this->pass();
    }
    else {
      $this->fail("upload $Url not found\n");
    }
    /* upload from file only stores the filename */
    $FileName = basename($File);
    if(array_key_exists($FileName,$jobs)) {
      $this->pass();
    }
    else {
      $this->fail("upload $FileName not found\n");
    }
    /*
     * uploads exist, but still, need to check email when they finish....
     */
    print "waiting for jobs to finish\n";
    $this->wait4jobs();
    print "verifying correct email was received\n";
    $this->checkEmailNotification(3);
  }
};
?>