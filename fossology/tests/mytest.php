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
require_once ('fossologyTestCase.php');

global $URL;

/* The class name should end in Test */

class myFirstTest extends fossologyTestCase
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
    print "setUP myFirstTest\n";
    $this->Login();
  }
/* all runnable test names (methods/functions) must start with 'test' */
  function testmytest()
  {
    global $URL;
    print "starting testmytest\n";
    $page = $this->mybrowser->get($URL);
    //print "page after get is:\n$page\n";
    $page = $this->mybrowser->clickLink('Browse');
    //print "page after Browse is:\n$page\n";
    $this->assertTrue($this->myassertText($page,'/Folder Navigation/'),
                      "FAIL! There is no Folder Navigation Title\n");
    $page = $this->mybrowser->clickLink('Create');
    $this->createFolder('Testing', 'New', "New Scheme");
    print "mytest: After createFolder\n";
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
