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
 * Clean up test data from a test run
 *
 * @param URL obtained from the test enviroment globals
 *
 * @version "$Id: testCleanUp.php 3009 2010-04-08 01:16:52Z rrando $"
 *
 * Created on Dec. 10, 2008
 */

require_once ('fossologyTestCase.php');
require_once ('TestEnvironment.php');

global $URL;

class cleanupTestData extends fossologyTestCase {
  public $mybrowser;
  public $webProxy;

  function setUp() {
    global $URL;
    $this->Login();
  }

  function testRmTestingFolders() {
    global $URL;

    $folderList = array('Basic-Testing',
                        'Testing',
                        'Agent-Test',
                        'SrvUploads',
                        'Enote',
    										'Copyright');

    print "Removing Testing folders\n";
    $page = $this->mybrowser->get($URL);
    foreach($folderList as $folder) {
      $this->deleteFolder($folder);
    }
  }

  function testRmUploads() {

    print "Removing ALL uploads in the root folder\n";
    $tr = TESTROOT;
    if(!chdir(TESTROOT)) {
      print "ERROR! could not cd to $tr\n";
      print "please run $tr" . "/cleanRF.php by hand\n";
    }
    $uploadLast = exec("fo-runTests  cleanRF.php -n 'Clean Root Folder'", $dummy, $Urtn);
    //print "DB: last line is:$uploadLast\n";
    //print "DB: results are:\n";print_r($dummy) . "\n";
  }
}
?>
