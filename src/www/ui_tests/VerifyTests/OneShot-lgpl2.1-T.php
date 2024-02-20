<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Upload a file from the server using the UI
 *
 *@TODO need to make sure testing folder exists....
 *@TODO THis test is for bsam, change for fnomos, make a new one for bsam.
 *
 * @version "$Id: OneShot-lgpl2.1-T.php 2472 2009-08-24 19:35:52Z rrando $"
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
    $this->Login();
  }

  function testOneShotTablegplv21()
  {
    global $URL;

    print "starting OneShotgplv21Test-Table\n";

    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Upload/'),
           "OneShotgplv21Test-Table FAILURE! Did not find Upload Menu\n");
    $this->assertTrue($this->myassertText($loggedIn, '/One-Shot Analysis/'),
           "OneShotgplv21Test-Table FAILURE! Did not find One-Shot Analysis Menu\n");

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
    //print "************ page a text (after analysis) *************\n$page\n";
    $this->assertTrue($this->myassertText($page, '/One-Shot License Analysis/'),
           "OneShotgplv21Test-Table FAILURE! Did not find One-Shot License Analysis Title\n");
    $this->assertTrue($this->myassertText($page, '/Match/'),
           "OneShotgplv21Test-Table FAILURE! Did not find text 'Match' \n");
    $this->assertTrue($this->myassertText($page, '/100%/'),
           "OneShotgplv21Test-Table FAILURE! Did not find '100percent\n");
    $this->assertTrue($this->myassertText($page, '/LGPL v2\.1 Preamble/'),
               "OneShotgplv21Test-Table FAILURE! Did not find 'LGPL v2.1 Preamble' \n");
    $this->assertFalse($this->myassertText($page, '/-partial/'),
           "OneShotgplv21Test-Table FAILURE! Found -partial in a non partial license file\n");
  }
}
