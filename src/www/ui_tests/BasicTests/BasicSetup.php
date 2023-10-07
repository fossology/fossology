<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Create a folder for use by the Basic tests
 *
 * @version "$Id: BasicSetup.php 1977 2009-04-08 04:01:03Z rrando $"
 *
 * Created on Oct. 15, 2008
 */

require_once ('../../../tests/fossologyTest.php');
require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

/* every test must use these globals, at least $URL */
global $URL;

class BasicFolderTest extends fossologyTestCase
{
  public $folder_name;
  public $mybrowser;

  function setUp()
  {
    global $URL;
    $this->Login();
  }

  function testBasicFolder()
  {
    global $URL;

    print "starting BasicFoldertest\n";
    $this->createFolder(null, 'Basic-Testing', null);
  }
}
