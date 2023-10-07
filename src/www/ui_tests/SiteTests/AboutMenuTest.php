<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Test to check the Help/About menu item, make sure page loads
 *
 * @version "$Id: AboutMenuTest.php 1362 2008-09-24 17:49:38Z rrando $"
 *
 * Created on Jul 21, 2008
 */

require_once('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;
global $USER;
global $PASSWORD;

class TestAboutMenu extends fossologyTestCase
{
  public $mybrowser;

  function testMenuAbout()
  {
    global $URL;
    print "starting testMenuAbout\n";
    $mybrowser = new SimpleBrowser();
    $page = $this->mybrowser->get($URL);
    $this->assertTrue($page);
    $this->myassertText($page, '/Welcome to FOSSology/');
    $this->mybrowser->click('Help');
    $this->mybrowser->click('About');
    $this->myassertText($page, '/About FOSSology/');
  }
}
