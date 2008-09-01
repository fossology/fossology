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
 * Template to use for a simpletest test
 *
 * @param
 *
 * @return
 *
 * @version "$Id: templateTest.php 1210 2008-08-27 19:50:04Z rrando $"
 *
 * Created on Aug 1, 2008
 */

/* every test must use these includes, adjust the paths based on where the
 * tests are in the source tree.
 */
require_once ('TestEnvironment.php');

global $URL;

/* The class name should end in Test */

/* NOTE: You MUST remove the abstract or the test will not get run */
class myFirstTest extends createFolder
{
  public $mybrowser;
  public $testFolder;

  /*
   * Every Test needs to login so we use the setUp method for that.
   * setUp is called before any other method by default.
   *
   * If other actions like creating a folder or something are needed,
   * put them in the setUp method after login.
   *
   */
  function setUp()
  {
    global $URL;
    print "setUP myFirstTest\n";
    $this->mybrowser = & new SimpleBrowser();
    $this->assertTrue(is_object($this->mybrowser));
    $page = $this->mybrowser->get($URL);
    $this->assertTrue($page);
    $cookie = $this->repoLogin($this->mybrowser,NULL,NULL);
    $host = $this->getHost($URL);
    $this->mybrowser->setCookie('Login', $cookie, $host);
  }

  function testmytest()
  {
    global $URL;
    print "starting testmytest\n";
    print "url is:$URL\n";
    $this->atst('From MyTest');
    $page = $this->mybrowser->get($URL);
    print "after mybrowser->get\n";
    //print "page after get is:\n$page\n";
    $page = $this->mybrowser->clickLink('Browse');
    print "after mybrowser->clickLink('Browse')\n";
    //print "page after Browse is:\n$page\n";
    $this->assertTrue($this->myassertText($page,'/Folder Navigation/'),
                      "FAIL! There is no Folder Navigation Title\n");
    print "after AssTRUE 'Folder Navigation'\n";
    $page = $this->mybrowser->clickLink('Create');
    print "mytest: calling createAFolder\n";
    $this->createAFolder('Testing', 'ATFX', "DuhX");
    print "mytest: After createAFolder\n";
  }

  /* use the tearDown method to clean up after a test.  This method like
   * setUp will run after every test.

   function tearDown()
   {
     return(TRUE);
   }
   */
}
?>
