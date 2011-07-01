<?php
/***********************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
 * @version "$Id$"
 *
 * Created on April 3, 2009
 */

$where = dirname(__FILE__);

if(preg_match('!/home/jenkins.*?tests.*!', $where, $matches))
{
  //echo "running from jenkins....fossology/tests\n";
  require_once('../../tests/fossologyTestCase.php');
  require_once ('../../tests/TestEnvironment.php');
}
else
{
  //echo "using requires for running outside of jenkins\n";
  require_once('../../../tests/fossologyTestCase.php');
  require_once ('../../../tests/TestEnvironment.php');
}

global $URL;


class uploadWoutEMailTest extends fossologyTestCase {

  public $mybrowser;

  public function setUp() {
    global $URL;

    $TR = TESTROOT;
    $_ENV['TestRoot'] = $TR;

    if (array_key_exists('WORKSPACE', $_ENV))
    {
      //echo "WORKSPACE EXISTS:{$_ENV['WORKSPACE']}\n";
      $path = $_ENV['WORKSPACE'] . "/fossology/ui/tests/EmailNotification/changeENV.php";
      global $WORKSPACE;
    }
    else
    {
      $path = './changeENV.php';
    }
    // change the user in TestEnvironment to noemail
    $last = exec("$path -c noemail -t $TR", $out, $rtn);
    if($rtn > 0) {
      $this->fail("Could not change the test environment file\n");
      print "Failure, output from changeENV is:\n";print_r($out) . "\n";
    }
    $this->Login();
    $result = $this->createFolder(1, 'Enote', 'Folder for Email notification uploads');
    if(!is_numeric($result)) {
      if($result != 'Folder Enote Exists') {
        $this->fail("Failure! folder Enote does not exist, Error is:$result\n");
      }
    }
  }

  public function testUploadWoutEmail() {

    global $URL;
    global $WORKSPACE;

    /* login noemail */
    print "Starting upload without email notificiation\n";
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

    /*
     * need to check email when they finish....
     */
    print "waiting for jobs to finish\n";
    if(!$this->wait4jobs())
    {
      echo "FATAL! Wait4Jobs failed...\n";
      $this->fail("Wait4Jobs failed, cannot verify that no email was received\n");
    }
    print "verifying  NO email was received\n";
    $this->checkEmailNotification(0);
  }

  public function tearDown() {

    global $WORKSPACE;
    
    if(array_key_exists('TestRoot', $_ENV))
    {
      $GTR = $_ENV['TestRoot'];
    }
    else
    {
      $msg = "No TestRoot environment varilable defined." .
        "Cannot change TestEnvironment file back to fosstester\n";
      $this->fail($msg);
      return;
    }
    print "Changing user back to fosstester";
    if (array_key_exists('WORKSPACE', $_ENV))
    {
      $WORKSPACE = $_ENV['WORKSPACE'];
      $path = "$WORKSPACE" . "/fossology/ui/tests/EmailNotification/changeENV.php";
    }
    else
    {
      $path = './changeENV.php';
    }
    $last = exec("$path -c fosstester -t $GTR", $out, $rtn);
    if($rtn > 0) {
      $this->fail("Could not change the test environment file\n");
      print "Failure, output from changeENV is:\n";print_r($out) . "\n";
      //exit(1);
    }
  }
};
?>