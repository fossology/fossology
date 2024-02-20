<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Create a folder then delete it.  This is a test to ensure we don't
 * regress in this area.  Use the root folder as the parent folder.
 *
 * @version "$Id: createFldrDeleteIt.php 2472 2009-08-24 19:35:52Z rrando $"
 *
 * Created on Dec 12, 2008
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class CreateDeleteFldrTest extends fossologyTestCase
{
  public $folder_name;
  public $mybrowser;

  function setUp()
  {
    global $URL;
    $this->Login();
    $this->folder_name = 'CreateDeleteFolderTest';
  }

  function testCreateDeleteFldr()
  {
    print "starting CreateDeleteFolderTest\n";
    // create then remove 5 times....
    for($i=0; $i<5; $i++) {
      $this->createFolder(1,$this->folder_name,
      'Folder created by CreateFolderTest as subfolder of Root Folder');
      $this->deleteFolder($this->folder_name);
      sleep(120);            // give delete job some time to remove it...
    }
  }
}
