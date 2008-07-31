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

class UploadFileMenuTest extends fossologyWebTestCase
{

  function testUploadFileMenu()
  {
    print "starting UploadFileMenuTest\n";
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
    $this->assertTrue($this->assertText($loggedIn, '/Upload/'));
    $this->assertTrue($this->assertText($loggedIn, '/Instructions/'));
    $this->assertTrue($this->assertText($loggedIn, '/From File/'));
    $this->assertTrue($this->assertText($loggedIn, '/From Server/'));
    $this->assertTrue($this->assertText($loggedIn, '/From URL/'));
    $this->assertTrue($this->assertText($loggedIn, '/One-Shot License/'));
    /* ok, this proves the text is on the page, let's see if we can
     * get to the delete page.
     */
    $page = $browser->get('http://osrb-1.fc.hp.com/repo/?mod=upload_file');
    $this->assertTrue($this->assertText($page, '/Upload a New File/'));
    $this->assertTrue($this->assertText($page, '/Select the file to upload:/'));
  }
}
?>
