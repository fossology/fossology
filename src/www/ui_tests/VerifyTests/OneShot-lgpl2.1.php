<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * Perform a one-shot license analysis
 *
 *@TODO needs setup and account to really work well...
 *
 * @version "$Id: OneShot-lgpl2.1.php 2591 2009-10-15 21:32:49Z rrando $"
 *
 * Created on Aug 1, 2008
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class OneShotgplv21Test extends fossologyTestCase
{
  public $mybrowser;

  function setUp()
  {
    /* check to see if the user and material exist*/
    $this->assertTrue(file_exists('/home/fosstester/.bashrc'),
                      "OneShotgplv21Test FAILURE! .bashrc not found\n");
    $this->Login();
  }

  function testOneShotgplv21()
  {
    global $URL;

    print "starting OneShotgplv21Test\n";
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Upload/'),
                      "OneShotgplv21Test FAILED! Did not find Upload Menu\n");
    $this->assertTrue($this->myassertText($loggedIn, '/One-Shot Analysis/'),
                      "OneShotgplv21Test FAILED! Did not find One-Shot Analysis Menu\n");

    $page = $this->mybrowser->get("$URL?mod=agent_nomos_once");
    $this->assertTrue($this->myassertText($page, '/One-Shot License Analysis/'),
    "OneShotgplv21Test FAILED! Did not find One-Shot License Analysis Title\n");
    $this->assertTrue($this->myassertText($page, '/The analysis is done in real-time/'),
             "OneShotgplv21Test FAILED! Did not find real-time Text\n");

    $this->assertTrue($this->mybrowser->setField('licfile', '/home/fosstester/licenses/gplv2.1'));
    /* we won't select highlights' */
    $this->assertTrue($this->mybrowser->clickSubmit('Analyze!'),
                      "FAILED! Count not click Analyze button\n");
    /* Check for the correct analysis.... */
    $page = $this->mybrowser->getContent();
    $this->assertTrue($this->myassertText($page, '/LGPL_v2\.1/'),
    "OneShotgplv21Test FAILED! Did not find exactly 'LGPL_v2.1'\n");

    $this->assertTrue($this->myassertText($page, '/One-Shot License Analysis/'),
    "OneShotgplv21Test FAILED! Did not find One-Shot License Analysis Title\n");
    // should not see -partial anymore
    $this->assertFalse($this->myassertText($page, '/-partial/'),
    "OneShotgplv21Test FAILED! Found -partial in a non partial license file\n");
  }
}
