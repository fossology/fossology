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
 * @version "$Id$"
 *
 * Created on Aug 20, 2008
 */

require_once ('../testClasses/parseBrowseMenu.php');
require_once ('../fossologyWebTestCase.php');
require_once ('../TestEnvironment.php');

global $URL;

class testParseBrowseMenu extends fossologyWebTestCase
{
  public $mybrowser;

  function setUp()
  {
    global $URL;
    print "TestparseBrowseMini is running\n";
    $browser = & new SimpleBrowser();
    $page = $browser->get($URL);
    $this->assertTrue($page);
    $this->assertTrue(is_object($browser));
    $this->mybrowser = $browser;
    $cookie = $this->repoLogin($this->mybrowser);
    $this->host = $this->getHost($URL);
    $this->mybrowser->setCookie('Login', $cookie, $host);
  }
  function testBrowseMenu()
  {
    /*
     * The test data depends on having the special fossology test
     * archive uploaded.
     * First test is a mix of files and directories
     * Second test is only files
     * Third test is only a directory
     */
    $testPages = array (
      'http://snape.west/repo/?mod=browse&folder=1&show=detail&upload=155&item=49948',
      'http://snape.west/repo/?mod=browse&folder=1&show=detail&upload=155&item=50013',
      'http://snape.west/repo/?mod=browse&upload=155&item=49947&folder=1&show=detail'
                        );
    /* navigate to a page with one */
    foreach ($testPages as $link)
    {
      print "navigating to:\n";
      print "$link\n";
      $page = $this->mybrowser->get($link);
      $bmenu = new parseBrowseMenu($page);
      $parsed = $bmenu->parseBrowseMenuFiles();
      $parsed = $bmenu->parseBrowseFileMinis();
      $parsed = $bmenu->parseBrowseMenuDirs();
      //$this->assertEqual(count($parsed), 5);
    }
    print "DB: parsed is:\n"; print_r($parsed) . "\n";
  }
}
?>
