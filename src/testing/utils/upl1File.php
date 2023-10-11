<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * uplTestData
 * \brief Upload Test data to the repo
 *
 * Upload using upload from file, url.  Sets Mime-type, nomos and package
 * agents.
 *
 * @param URL obtained from the test enviroment globals
 *
 * @version "$Id: uplTestData.php 4132 2011-04-22 06:19:07Z rrando $"
 *
 * Created on Aug 15, 2008
 */

require_once ('fossologyTestCase.php');
require_once ('TestEnvironment.php');

global $URL;
global $PROXY;

class uploadTestDataTest extends fossologyTestCase
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
    print "Creating Testing folder\n";
    $page = $this->mybrowser->get($URL);
    $this->createFolder(null, 'Testing', null);

    print "Creating Copyright folder\n";
    $this->createFolder(null, 'Copyright', null);
  }

  function testuploadTestDataTest() {

    global $URL;
    global $PROXY;

    print "starting testUploadTestData\n";
    $rootFolder = 1;
    $upload = NULL;
    $uploadList = array('TestData/archives/foss23D1F1L.tar.bz2');


    /* upload the archives using the upload from file menu
     *
     * 1 = bucket agent
     * 2 = copyright agent
     * 3 = mime agent
     * 4 = metadata agent
     * 5 = nomos agent
     * 6 = package agent
     */

    print "Starting file uploads\n";
    foreach($uploadList as $upload) {
      $description = "File $upload uploaded by Upload Test Data Test";
      $this->uploadFile('Testing', $upload, $description, null, '1,2,3,4');
    }
  }
}
