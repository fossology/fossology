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
 * Created on Aug 11, 2008
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class OneShotTablegplv21Test extends fossologyTestCase
{
  function setUp()
  {
    /* check to see if the user and material exist*/
    $this->assertTrue(file_exists('/home/fosstester/.bashrc'),
                      "FAILURE! .bashrc not found\n");
    $this->assertTrue(file_exists('/home/fosstester/ReadMe'),
                      "FAILURE! Readme in ~fosstester not found\n");
    $this->Login($browser);
  }

  function testOneShotTablegplv21()
  {
    global $URL;

    print "starting OneShotgplv21Test-Table\n";

    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Upload/'),
           "OneShotgplv21Test-Table FAILURE! Did not find Upload Menu\n");
    $this->assertTrue($this->myassertText($loggedIn, '/One-Shot License/'),
           "OneShotgplv21Test-Table FAILURE! Did not find One-Shot License Menu\n");

    $page = $this->mybrowser->get("$URL?mod=agent_license_once");
    $this->assertTrue($this->myassertText($page, '/One-Shot License Analysis/'),
           "OneShotgplv21Test-Table FAILURE! Did not find One-Shot License Analysis Title\n");
    $this->assertTrue($this->myassertText($page, '/The analysis is done in real-time/'),
           "OneShotgplv21Test-Table FAILURE! Did not find real-time Text\n");
    $this->assertTrue($this->mybrowser->setField('licfile', '/home/fosstester/licenses/gplv2.1'));
    /* select highlights' */
    $this->assertTrue($this->mybrowser->setField('highlight', 1),
           "OneShotgplv21Test-Table FAILURE! Could not click  highlight\n");
    $this->assertTrue($this->mybrowser->clickSubmit('Analyze!'),
           "OneShotgplv21Test-Table FAILURE! Could not click Analyze button\n");
    /* Check for the correct analysis....it should be 100% match, no partials */
    //$page = $this->mybrowser->getContentAsText();
    $page = $this->mybrowser->getContent();
    print "************ page a text (after analysis) *************\n$page\n";
    $this->assertTrue($this->myassertText($page, '/One-Shot License Analysis/'),
           "OneShotgplv21Test-Table FAILURE! Did not find One-Shot License Analysis Title\n");
    $this->assertTrue($this->myassertText($page, '/Match/'),
           "OneShotgplv21Test-Table FAILURE! Did not find text 'Match' \n");
    $this->assertTrue($this->myassertText($page, '/100% view LGPL v2\.1/'),
           "OneShotgplv21Test-Table FAILURE! Did not find '100percent view LGPL v2.1' \n");
    $this->assertFalse($this->myassertText($page, '/-partial/'),
           "OneShotgplv21Test-Table FAILURE! Found -partial in a non partial license file\n");
  }
}
?>
