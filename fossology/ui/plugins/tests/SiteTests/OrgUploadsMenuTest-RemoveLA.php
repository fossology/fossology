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
require_once ('../../../../tests/fossologyTestCase.php');
require_once ('../../../../tests/TestEnvironment.php');

global $URL;

class UploadsRemoveLAMenuTest extends fossologyTestCase
{

  function testUploadsRemoveLAMenu()
  {
    global $URL;
    print "starting UploadsRemoveLAMenuTest\n";
    $this->Login($browser);
    $host = $this->getHost($URL);
    $browser->setCookie('Login', $cookie, $host);
    /* we get the home page to get rid of the user logged in page */
    $loggedIn = $browser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Organize/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Uploads/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Delete Uploaded File/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Edit Properties \(TBD\)/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Move/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Remove License Analysis/'));
    /* ok, this proves the text is on the page, let's see if we can
     * get to the delete page.
     */
    $page = $browser->get("$URL?mod=agent_reset_license");
    $this->assertTrue($this->myassertText($page, '/Remove License Analysis/'));
    $this->assertTrue($this->myassertText($page, '/THERE IS NO UNREMOVE/'));
  }
}
?>
