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
 * @version "$Id: $"
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
  }
  /*
   * at this point one could wait for the jobs to end and verify.  We will not
   * do this at this time.  The suite will wait for the jobs to end and verify
   * the email was received locally on the test system.
   */
};
?>