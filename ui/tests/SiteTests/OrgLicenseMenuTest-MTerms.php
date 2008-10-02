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
 * @version "$Id$"
 *
 * Created on Jul 31, 2008
 */
require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class LicenseMTermsMenuTest extends fossologyTestCase
{

  function testLicenseMTermsMenu()
  {
    global $URL;
    $this->Login($browser);
    /* we get the home page to get rid of the user logged in page */
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Organize/'));
    $this->assertTrue($this->myassertText($loggedIn, '/License/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Default Groups/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Default Terms/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Manage Groups/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Manage Terms/'));
    /* ok, this proves the text is on the page, let's see if we can
     * get to the delete page.
     */
    $page = $this->mybrowser->get("$URL?mod=licterm_manage");
    $this->assertTrue($this->myassertText($page, '/Manage License Terms/'));
    $this->assertTrue($this->myassertText($page, '/function moveOptions/'));
  }
}
?>
