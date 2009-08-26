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
 * Upload Test data for use in agent add and email notification tests
 *
 * Uses the simpletest framework, this way it doesn't matter where the
 * repo is, it will get uploaded, and this is another set of tests.
 *
 * @param URL obtained from the test enviroment globals
 *
 * @version "$Id$"
 *
 * Created on March 18, 2009
 */
/* Upload the following files from the fosstester home directory:
 * - simpletest_1.0.1.tar.gz
 * - gplv2.1
 * - Affero-v1.0
 * - http://www.gnu.org/licenses/gpl-3.0.txt
 * - http://www.gnu.org/licenses/agpl-3.0.txt
 */
require_once ('fossologyTestCase.php');
require_once ('TestEnvironment.php');
global $URL;
global $PROXY;
class uploadAgentDataTest extends fossologyTestCase {
  public $mybrowser;
  public $webProxy;

  function setUp() {
    global $URL;
    $this->Login();
  }
  /**
   * create the Testing folder used by other tests
   */
  function testCreateTestingFolder() {
    global $URL;
    print "Creating Agent-Test folder\n";
    $page = $this->mybrowser->get($URL);
    $this->createFolder(NULL, 'Agent-Test', NULL);
  }
  function testuploadAgentDataTest() {

    global $PROXY;
    $Svn = `svnversion`;
    $date = date('Y-m-d');
    $time = date('h:i:s-a');
    print "Starting uploadAgentDataTest on: " . $date . " at " . $time . "\n";
    print "Using Svn Version:$Svn\n";
    $LicenseList = array('TestData/licenses/RCSL_v3.0_a.txt',
                            'TestData/licenses/BSD_style_a.txt',
                            'TestData/licenses/BSD_style_b.txt',
                            'TestData/licenses/BSD_style_c.txt',);

    $urlList = array('http://downloads.sourceforge.net/simpletest/simpletest_1.0.1.tar.gz',
                         'http://www.gnu.org/licenses/gpl-3.0.txt',
                         'http://www.gnu.org/licenses/agpl-3.0.txt',
                         'http://snape.west/~fosstester/fossDirsOnly.tar.bz2');
    /* upload the archives using the upload from file menu */
    //$desciption = "File $upload uploaded by Upload Data Test";
    print "Starting file uploads\n";
    // we do them serially for now due to different parameters needed
    $this->uploadFile('Agent-Test', $LicenseList[0],
         "File $LicenseList[0] uploaded by Upload Data Test", NULL, NULL);
    $this->uploadFile('Agent-Test', $LicenseList[1],
         "File $LicenseList[1] uploaded by Upload Data Test", NULL, 1);
    $this->uploadFile('Agent-Test', $LicenseList[2],
         "File $LicenseList[2] uploaded by Upload Data Test", NULL, '1,2');
    $this->uploadFile('Agent-Test', $LicenseList[3],
         "File $LicenseList[3] uploaded by Upload Data Test", NULL, 3);

    /* Upload the urls using upload from url.  Check if the user specificed a
     * web proxy for the environment.  If so, set the attribute. */
    if (!(empty($PROXY))) {
      $this->webProxy = $PROXY;
    }
    print "Starting Url uploads\n";
    foreach($urlList as $url) {
      $this->uploadUrl('Agent-Test', $url, NULL, NULL, NULL);
    }
  }
}
?>
