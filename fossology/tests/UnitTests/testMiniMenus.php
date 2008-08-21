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
 * use/test the parseminimenu class
 *
 * @param
 *
 * @return
 *
 * @version "$Id: $"
 *
 * Created on Aug 20, 2008
 */

require_once ('../tests/testClasses/parseMiniMenu.php');
require_once ('../tests/fossologyWebTestCase.php');
require_once ('../tests/TestEnvironment.php');

global $URL;

class testpmini extends fossologyWebTestCase
{
    public $mybrowser;

  function setUp()
  {
    global $URL;
    print "Trymini is running\n";
    $browser = & new SimpleBrowser();
    $page = $browser->get($URL);
    $this->assertTrue($page);
    $this->assertTrue(is_object($browser));
    $this->mybrowser = $browser;
    $cookie = $this->repoLogin($this->mybrowser);
    $this->host = $this->getHost($URL);
    $this->mybrowser->setCookie('Login', $cookie, $host);
  }
  function testmini()
  {
    /* navigate to a page with one */
    //print "Trymini is running testmini\n";
    $page = $this->mybrowser->get
    ('http://snape.west/~markd/ui-md/?mod=browse&upload=70&folder=1&item=36691&show=detail');
    //9('http://snape.west/~markd/ui-md/?mod=view&upload=151&show=detail&item=49535');
    //5('http://snape.west/repo/?mod=browse&upload=149&folder=1&item=49401&show=detail');
    $mini = new parseMiniMenu($page);
    $parsed = $mini->parseMiniMenu();
    $this->assertEqual(count($parsed), 5);
    //$this->assertEqual(count($parsed), 9);
    //print "DB: parsed is:\n"; print_r($parsed) . "\n";
  }
}
?>
