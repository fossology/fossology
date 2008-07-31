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
 * @version "$Id: $"
 *
 * Created on Jul 31, 2008
 */
require_once ('../../../../tests/fossologyWebTestCase.php');

//error_reporting(E_ALL);

class UploadsDeleteMenuTest extends fossologyWebTestCase
{

  function testUploadsDeleteMenu()
  {
    print "starting UploadsDeleteMenuTest\n";
    $this->useProxy('http://web-proxy.fc.hp.com:8088', 'web-proxy', '');
    $browser = & new SimpleBrowser();
    $page = $browser->get('http://osrb-1.fc.hp.com/repo/');
    $this->assertTrue($page);
    $this->assertTrue(is_object($browser));
    $cookie = $this->repoLogin($browser);
    $browser->setCookie('Login', $cookie, 'osrb-1.fc.hp.com');
    $loggedIn = $browser->getContent();
    /* we get the home page to get rid of the user logged in page */
    $page = $browser->get('http://osrb-1.fc.hp.com/repo/');
    $this->assertTrue($this->assertText($loggedIn, '/Organize/'));
    $this->assertTrue($this->assertText($loggedIn, '/Uploads/'));
    $this->assertTrue($this->assertText($loggedIn, '/Delete Uploaded File/'));
    $this->assertTrue($this->assertText($loggedIn, '/Edit Properties (TBD)/'));
    $this->assertTrue($this->assertText($loggedIn, '/Move/'));
    $this->assertTrue($this->assertText($loggedIn, '/Remove License Analysis/'));
    /* ok, this proves the text is on the page, let's see if we can
     * get to the delete page.
     */
    $page = $browser->get('http://osrb-1.fc.hp.com/repo/?mod=admin_upload_delete');
    $this->assertTrue($this->assertText($page, '/Delete Uploaded File/'));
    $this->assertTrue($this->assertText($page, '/THERE IS NO UNDELETE/'));
  }
}
?>
