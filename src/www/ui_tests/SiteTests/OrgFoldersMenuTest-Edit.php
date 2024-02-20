<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Is the folder edit properties menu available?
 *
 * @version "$Id: OrgFoldersMenuTest-Edit.php 1924 2009-03-27 01:45:55Z rrando $"
 *
 * Created on Jul 31, 2008
 */
require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class FoldersEditMenuTest extends fossologyTestCase
{

  function testFolderEditMenu()
  {
    print "Starting OrgFoldersMenuTest-Edit\n";
    global $URL;
    $this->Login();
    /* we get the home page to get rid of the user logged in page */
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Organize/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Folders /'));
    $this->assertTrue($this->myassertText($loggedIn, '/Create/'));
    /* ok, this proves the text is on the page, let's see if we can
     * get to the edit page.
     */
    $page = $this->mybrowser->get("$URL?mod=folder_properties");
    $this->assertTrue($this->myassertText($page, '/Edit Folder Properties/'));
    $this->assertTrue($this->myassertText($page, '/Change folder name:/'));
  }
}
