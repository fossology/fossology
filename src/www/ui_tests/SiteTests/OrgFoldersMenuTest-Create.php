<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Site Level test to verify Organize->Folder->* menus exist
 *
 *
 * @version "$Id: OrgFoldersMenuTest-Create.php 1362 2008-09-24 17:49:38Z rrando $"
 *
 * Created on Jul 24, 2008
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;
global $USER;
global $PASSWORD;

class FoldersCreateMenuTest extends fossologyTestCase
{
  public $mybrowser;

  function testCreateFolderMenu()
  {
    global $URL;
    print "starting OrgFolderCreateMenuTest\n";

    $this->Login();
    $loggedIn = $this->mybrowser->get($URL);
    /* we get the home page to get rid of the user logged in page */
    $page = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Organize/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Folders /'));
    $this->assertTrue($this->myassertText($loggedIn, '/Create/'));
    /* ok, this proves the text is on the page, let's see if we can
     * get to the create page.
     */
    $page = $this->mybrowser->get("$URL?mod=folder_create");
    $this->assertTrue($this->myassertText($page, '/Create a new Fossology folder/'));
  }
}
