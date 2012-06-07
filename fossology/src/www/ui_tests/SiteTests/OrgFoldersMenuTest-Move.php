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
 * Is the folder edit properties menu available?
 *
 * @version "$Id: OrgFoldersMenuTest-Move.php 4017 2011-03-31 20:24:42Z rrando $"
 *
 * Created on Jul 31, 2008
 */
require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class FoldersMoveMenuTest extends fossologyTestCase
{

  function testFolderMoveMenu()
  {
    global $URL;
    print "starting OrgFolderMoveMenuTest\n";

    $this->Login();
    /* we get the home page to get rid of the user logged in page */
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Organize/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Folders /'));
    $this->assertTrue($this->myassertText($loggedIn, '/Create/'),
      "Organize->Folders->Create NOT found!\n");
    /* ok, this proves the text is on the page, let's see if we can
     * get to the move page.
     */
    $page = $this->mybrowser->get("$URL?mod=folder_move");
    $this->assertTrue($this->myassertText($page, '/Move Folder/'));
    $this->assertTrue($this->myassertText($page, '/destination folder:/'));
  }
}
?>
