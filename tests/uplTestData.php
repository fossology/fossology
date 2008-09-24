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
 * Upload Test data to the repo
 *
 * Uses the simpletest framework, this way it doesn't matter where the
 * repo is, it will get uploaded, and this is another set of tests.
 *
 * @param URL obtained from the test enviroment globals
 *
 * @version "$Id$"
 *
 * Created on Aug 15, 2008
 */

/* Upload the following files from the fosstester home directory:
 * - simpletest_1.0.1.tar.gz
 * - gplv2.1
 * - Affero-v1.0
 * - http://www.gnu.org/licenses/gpl-3.0.txt
 * - http://www.gnu.org/licenses/agpl-3.0.txt
 */

//require_once '/usr/local/simpletest/autorun.php';
require_once ('fossologyTestCase.php');
require_once ('TestEnvironment.php');

global $URL;

class uploadTestDataTest extends fossologyTestCase
{
  public $mybrowser;

    function setUp()
  {
    global $URL;
    $this->mybrowser = & new SimpleBrowser();
    $this->assertTrue(is_object($this->mybrowser));
    $page = $this->mybrowser->get($URL);
    $this->assertTrue($page);
    $cookie = $this->repoLogin($this->mybrowser);
    $host = $this->getHost($URL);
    $this->mybrowser->setCookie('Login', $cookie, $host);
  }

  /**
   * create the Testing folder used by other tests
   */
  function testCreateTestingFolder()
  {
    $page = $this->mybrowser->get($URL);
    $page = $this->mybrowser->clickLink('Create');
    $this->assertTrue($this->assertText($page, '/Create a new Fossology folder/'));
    $this->assertTrue($this->mybrowser->setField('parentid', 1));
    $this->assertTrue($this->mybrowser->setField('newname', 'Testing'));
    $desc = 'Folder created by uplTestData as subfolder of Root Folder';
    $this->assertTrue($this->mybrowser->setField('description', "$desc"));
    $page = $this->mybrowser->clickSubmit('Create!');
    $this->assertTrue(page);
    $this->assertTrue($this->assertText($page, "/Folder Testing Created/"),
                      "FAIL! Folder Testing Created not found\n");
  }

  function testuploadTestDataTest()
  {
    global $URL;
    print "starting testUploadTestData\n";
    $rootFolder = 1;
    $uploadList = array('TestData/archives/fossI16L499.tar.bz2',
                        'TestData/licenses/gplv2.1',
                        'TestData/licenses/Affero-v1.0');
    $urlList = array('http://downloads.sourceforge.net/simpletest/simpletest_1.0.1.tar.gz',
                     'http://www.gnu.org/licenses/gpl-3.0.txt',
                     'http://www.gnu.org/licenses/agpl-3.0.txt');

    /* upload the archives using the upload from file menu */
    foreach($uploadList as $upload)
    {
      $this->uploadAFile($rootFolder, $upload, 'Testing', null, '1,2,3');
    }
    /* Upload the urls using upload from url */
    foreach($urlList as $url)
    {
      $this->uploadAUrl($rootFolder, $url, null, null, '1,2,3');
    }
  }
}
?>
