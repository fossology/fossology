<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Test to check that the search menu exists and the page loads
 *
 * @version "$Id: SearchMenuTest.php 1362 2008-09-24 17:49:38Z rrando $"
 *
 * Created on Jul 23, 2008
 */

require_once('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;
global $USER;
global $PASSWORD;

class SearchMenuTest extends fossologyTestCase
{
  public $mybroswer;

  function testSearchMenu()
  {
    global $URL;
    print "starting SearchMenuTest\n";
    $browser = new SimpleBrowser();
    $this->setBrowser($browser);
    $page = $this->mybrowser->get($URL);
    $this->assertTrue($page);
    $this->myassertText($page, '/Welcome to FOSSology/');
    $page = $this->mybrowser->click('Search');
    $this->myassertText($page, '/Search for File/');
    $this->myassertText($page, '/Enter the filename to find/');
  }
}
