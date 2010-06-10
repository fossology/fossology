<?php
/***********************************************************
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
 * uploadCopyrightData
 * \brief Upload Test data for copyright tests to the repo
 * 
 * Upload using upload from file.  Sets copyright, nomos agents.
  *
 * @param URL obtained from the test enviroment globals
 *
 * @version "$Id:  $"
 *
 * Created on June 10, 2010
 */

require_once ('fossologyTestCase.php');
require_once ('TestEnvironment.php');

global $URL;
global $PROXY;

class uploadCopyRdata extends fossologyTestCase
{
  public $mybrowser;
  public $webProxy;

    function setUp()
  {
    global $URL;
    $this->Login();
  }

  /**
   * create the Copyright folder used by these
   */
  function testCreateTestingFolder()
  {
    global $URL;

    print "Creating Copyright folder\n";
    $this->createFolder(null, 'Copyright', null);
  }

  function testUploadCopyRTest() {

    global $URL;

    print "starting testUploadCopyRTest\n";
    $rootFolder = 1;
    $upload = NULL;
    
    $copyrightList = array ('TestData/archives/3files.tar.bz2',
    												'../agents/copyright_analysis/testdata/tdata1',
    												'../agents/copyright_analysis/testdata/tdata2',
    												'../agents/copyright_analysis/testdata/tdata3',
    												'../agents/copyright_analysis/testdata/tdata4',
    												'../agents/copyright_analysis/testdata/tdata5'
    												);

    /* upload the archives using the upload from file menu
     * 
     * 1 = bucket agent
     * 2 = copyright agent
     * 3 = mime agent 
     * 4 = metadata agent
     * 5 = nomos agent
     * 6 = package agent
     */
    
    print "Starting copyright uploads\n";
    foreach($copyrightList as $upload) {
      $description = "File $upload uploaded by Upload Test Data Test";
      $this->uploadFile('Copyright', $upload, $description, null, '2,3,5');
    }
  }
}
?>
