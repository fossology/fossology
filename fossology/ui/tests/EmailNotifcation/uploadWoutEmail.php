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
 * uploadWoutEmail
 *
 * Upload files as a user without email notification
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

class uploadWoutEMailTest extends fossologyTestCase {

  public $mybrowser;

  public function setUp() {
    global $URL;
    $this->Login();
    $result = $this->createFolder(1, 'Enote', 'Folder for Email notification uploads');
    if(!is_null($result)) {
      if($result != 'Folder Enote Exists') {
        $this->fail("Failure! folder Enote does not exist, stopping test\n");
        exit(1);
      }
    }
    $this->Logout();
  }

  public function testUploadWoutEmail() {

    global $URL;

    /* login noemail */
    $this->Login('noemail','noemail');
    $page = $this->mybrowser->get($URL);

    $File = '/home/fosstester/licenses/gplv2.1';
    $Filedescription = "The GPL Version 2.1 from the gnu.org site";

    $Url = 'http://www.gnu.org/licenses/gpl.txt';
    $Urldescription = "The GPL Version 3.0 June 2007 from www.gnu.org/licenses/gpl.txt";

    $Srv = '/home/fosstester/licenses/zlibLicense-1.2.2-2004-Oct-03';
    $Srvdescription = "zlib license from http://www.gzip.org/zlib/zlib_license.html";

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