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
 * Upload a file from the server using the UI
 *
 *@TODO need to make sure testing folder exists....
 *@TODO needs setup and account to really work well...
 *
 * @version "$Id$"
 *
 * Created on Aug 1, 2008
 */

require_once ('../../../../tests/fossologyTestCase.php');
require_once ('../../../../tests/TestEnvironment.php');

global $URL;

class OneShotgplv21Test extends fossologyTestCase
{
  function setUp()
  {
    /* check to see if the user and material exist*/
    $this->assertTrue(file_exists('/home/fosstester/.bashrc'),
                      "FAILURE! .bashrc not found\n");
    $this->assertTrue(file_exists('/home/fosstester/ReadMe'),
                      "FAILURE! Readme in ~fosstester not found\n");
  }

  function testOneShotgplv21()
  {
    global $URL;

    print "starting OneShotgplv21Test\n";
    $this->useProxy('http://web-proxy.fc.hp.com:8088', 'web-proxy', '');
    $browser = & new SimpleBrowser();
    $page = $browser->get($URL);
    $this->assertTrue($page);
    $this->assertTrue(is_object($browser));
    $cookie = $this->repoLogin($browser);
    $host = $this->getHost($URL);
    $browser->setCookie('Login', $cookie, $host);

    $loggedIn = $browser->get($URL);
    $this->assertTrue($this->assertText($loggedIn, '/Upload/'),
                      "FAIL! Did not find Upload Menu\n");
    $this->assertTrue($this->assertText($loggedIn, '/One-Shot License/'),
                      "FAIL! Did not find One-Shot License Menu\n");

    $page = $browser->get("$URL?mod=agent_license_once");
    $this->assertTrue($this->assertText($page, '/One-Shot License Analysis/'),
                      "FAIL! Did not find One-Shot License Analysis Title\n");
    $this->assertTrue($this->assertText($page, '/The analysis is done in real-time/'),
                      "FAIL! Did not find real-time Text\n");

    $this->assertTrue($browser->setField('licfile', '/home/fosstester/licenses/gplv2.1'));
    /* we won't select highlights' */
    $this->assertTrue($browser->clickSubmit('Analyze!'),
                      "FAIL! Count not click Analyze button\n");
    /* Check for the correct analysis.... */
    $page = $browser->getContent();
    $this->assertTrue($this->assertText($page, '/LGPL/'),
                      "FAIL! Did not identify LGPL as LGPL\n");
    $this->assertTrue($this->assertText($page, '/v2\.1/'),
                      "FAIL! Did not find v2.1 version string\n");
        $this->assertTrue($this->assertText($page, '/One-Shot License Analysis/'),
                      "FAIL! Did not find One-Shot License Analysis Title\n");
    $this->assertFalse($this->assertText($page, '/-partial/'),
                      "FAIL! Found -partial in a non partial license file\n");

    //print "************ page after Analysis! *************\n$page\n";
  }
}
?>
