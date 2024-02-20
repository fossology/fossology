<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Can the folder delete menu be reached?
 *
 * @version "$Id: OrgFoldersMenuTest-Delete.php 2292 2009-07-01 18:25:11Z rrando $"
 *
 * Created on Jul 31, 2008
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;
global $USER;
global $PASSWORD;

class FoldersDeleteMenuTest extends fossologyTestCase
{
  public $mybrowser;

  function testFolderDeleteMenu() {

    global $URL;

    $this->Login();
    /* we get the home page to get rid of the user logged in page */
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Organize/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Folders /'));
    $this->assertTrue($this->myassertText($loggedIn, '/Create/'));
    /* ok, this proves the text is on the page, let's see if we can
     * get to the delete page.
     */
    $page = $this->mybrowser->get("$URL?mod=admin_folder_delete");
    $this->assertTrue($this->myassertText($page, '/Delete Folder/'));
    $this->assertTrue($this->myassertText($page, '/THERE IS NO UNDELETE/'));
  }
}
