<?php
/*
 SPDX-FileCopyrightText: © 2010 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * uploadUnpack
 * \brief Upload and unpack test data to the repo
 * 
 * Upload using upload from file, url.  NOTE, no agents are scheduled, 
 * the files should only be unpacked.  
 *
 * @param URL obtained from the test enviroment globals
 *
 * @version "$Id: uploadUnpack.php 3349 2010-07-28 01:20:52Z rrando $"
 *
 * Created on July 27, 2010
 */

require_once ('fossologyTestCase.php');
require_once ('TestEnvironment.php');

global $URL;
global $PROXY;

class uploadUnpack extends fossologyTestCase
{
  public $mybrowser;
  public $webProxy;

    function setUp()
  {
    global $URL;
    $this->Login();
  }

  /**
   * create the Testing folder used by other tests
   */
  function testCreateTestingFolder()
  {
    global $URL;
    print "Creating Code Coveragefolder\n";
    $page = $this->mybrowser->get($URL);
    $this->createFolder(null, 'Code Coverage', null);
  }

  function testuploadUnpack() {

    global $URL;
    global $PROXY;

    print "starting testuploadUnpack\n";
    $rootFolder = 1;
    $upload = NULL;
    $uploadList = array('TestData/archives/eddyData.tar.bz2',
                        'TestData/archives/foss23D1F1L.tar.bz2',
                        'TestData/licenses/gplv2.1',
                        'TestData/licenses/Affero-v1.0');
    $urlList = array('http://downloads.sourceforge.net/simpletest/simpletest_1.0.1.tar.gz',
                     'http://www.gnu.org/licenses/gpl-3.0.txt',
                     'http://www.gnu.org/licenses/agpl-3.0.txt',
                     'http://osrb-1.fc.hp.com/~fosstester/fossology_1.1.1~20100622_all.deb');
    
    print "Starting file uploads\n";
    foreach($uploadList as $upload) {
      $description = "File $upload uploaded by Upload Test Data Test";
      $this->uploadFile('Testing', $upload, $description, null, NULL);
    }

    /* Upload the urls using upload from url.  Check if the user specificed a
     * web proxy for the environment.  If so, set the attribute. */

    if(!(empty($PROXY)))
    {
      $this->webProxy = $PROXY;
    }
    print "Starting Url uploads\n";
    foreach($urlList as $url)
    {
      $this->uploadUrl($rootFolder, $url, null, null, NULL);
    }
  }
}
