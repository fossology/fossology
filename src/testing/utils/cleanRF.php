<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Clean up uploaded files from root folder (Software Repository)
 *
 * @param ?
 *
 * @version "$Id: cleanRF.php 2670 2009-12-08 18:39:59Z rrando $"
 *
 * Created on March 25, 2009
 */

require_once ('fossologyTestCase.php');
require_once ('TestEnvironment.php');

global $URL;

class cleanupRF extends fossologyTestCase {
  public $mybrowser;

  function setUp() {
    global $URL;
    $this->Login();
  }

  function testRmRFContent() {
    global $URL;
    print "Removing the content of the root folder (Software Repository)\n";
    $page = $this->mybrowser->get($URL);
    $page = $this->mybrowser->clickLink('Delete Uploaded File');
    $this->assertTrue($this->myassertText($page, '/Select the uploaded file to delete/'),
        "Could not select an uploaded file, (did not see the text)\n" . 
        "Make sure you are logged in a fossy\n");
    $SRselect = $this->parseSelectStmnt($page,'upload');
    //print "SRselect is:\n"; print_r($SRselect) . "\n";
    if(empty($SRselect)) {
      $this->pass('Nothing to remove');
      return;
    }
    foreach($SRselect as $uploadName => $uploadId){
      print "Removing $uploadName...\n";
       $this->assertTrue($this->mybrowser->setField('upload', $uploadId));
       $page = $this->mybrowser->clickSubmit('Delete!');
       $this->assertTrue($page);
       $this->assertTrue($this->myassertText($page, "/Deletion added to job queue/"),
       "delete Upload Failed!\nPhrase 'Deletion added to job queue' not found\n");
    }
  }
}
