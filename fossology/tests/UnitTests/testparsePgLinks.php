#!/usr/bin/php
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
 * @version "$Id: testparseBrowseMenus.php 1152 2008-08-21 03:58:31Z rrando $"
 *
 * Created on Aug 20, 2008
 */

require_once ('../testClasses/parsePgLinks.php');
require_once ('../fossologyWebTestCase.php');
require_once ('../TestEnvironment.php');

global $URL;

class testParsePgLinks extends fossologyWebTestCase
{
  public $mybrowser;

  function setUp()
  {
    global $URL;
    print "TestparsePgLinks is running\n";
    $browser = & new SimpleBrowser();
    $page = $browser->get($URL);
    $this->assertTrue($page);
    $this->assertTrue(is_object($browser));
    $this->mybrowser = $browser;
    $cookie = $this->repoLogin($this->mybrowser);
    $this->host = $this->getHost($URL);
    $this->mybrowser->setCookie('Login', $cookie, $host);
  }
  function testPPgLinks()
  {
    /*
     * The test data depends on having the special fossology test
     * archive uploaded and the full fossology sources as well as the
     *
     *First url is fossology repo front page
     *Second test is Testnig
     *folder
     *Third url is Broswe Screen
     *4th url is License Browser Screen
     */
    $testPages = array (
    //'http://osrb-1.fc.hp.com/repo/?mod=browse',
    //'http://osrb-1.fc.hp.com/repo/?mod=browse&folder=299',
    'http://osrb-1.fc.hp.com/repo/?mod=browse&folder=299&show=detail&upload=627&item=5066018',
    'http://osrb-1.fc.hp.com/repo/?mod=license&show=detail&upload=627&item=5066018',
                        );
    /*
     * testCounts is the number of table elements in each test
     *
     * This will make the this test a bit brittle, but needed to ensure
     * proper operation. Think about using mocks in the future.
     */
     $testCounts = array(1,5);

    /* navigate to a page to test*/
    $index = 0;
    foreach ($testPages as $link)
    {
      print "navigating to:\n";
      print "$link\n";
      $page = $this->mybrowser->get($link);
      //print "page is:$page\n";
      $LfileList = new parsePgLinks($page);
      $parsed = $LfileList->parsePgLinks();
      $fileCounts = count($parsed);
      $pathItems = count($parsed[$index]);
      print "parsed path is:\n"; print_r($parsed) . "\n";
      //$this->assertEqual($fileCounts,$testCounts[$index],
        //                 "FAIL! Number of files found did not match\n");
      /*
       * TODO: Extend this test when you get snape urls (above) and
       * count the number of path elements (includes leaf file) and
       * compare:
      $pathCounts = array(1,2,3);
      $this->assertEqual($pathItems,$pathCounts[$index],
                         "FAIL! number of path items did not match\n");
      */
      $index++;
    }
  }
}
?>
