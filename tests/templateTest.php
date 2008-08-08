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
 * @version "$Id: $"
 *
 * Created on Aug 1, 2008
 */

/* every test must use these includes, adjust the paths based on where the
 * tests are in the source tree.
 */
require_once ('../../../../tests/fossologyWebTestCase.php');
require_once ('../../../../tests/TestEnvironment.php');

/* every test must use these globals, at least $URL */
global $URL;
global $USER;
global $PASSWORD;

/* The class name should end in Test */

/* NOTE: You MUST remove the abstract or the test will not get run */
abstract class someTest extends fossologyWebTestCase
{

  /*
   * if you need to do some setup for the test, define a setup function
   * and the test framework will call in when the class is run.
   */
  function setup()
  {
    global $URL;
    return TRUE;
  }

  /*
   * usually the test will only have one method, start it with the word
   * test.
   *
   * Every Test should print a start message, this is useful to help
   * determine where a test failed.  Most assert's can
   *
   * Every test should login to the site, so that it can be run
   * standalone.  Use the repoLogin method defined in
   * fossologyWebTestCase.  Typically you create a browser and then use
   * that object to login with.  See below.
   *
   * The login routine will return the session cookie.  Use it to set
   * set the cookie.
   */
  function testSome()
  {
    global $URL;

    print "starting testSome\n";
    $browser = & new SimpleBrowser();
    $page = $browser->get($URL);
    $this->assertTrue($page);
    $this->assertTrue(is_object($browser));
    $cookie = $this->repoLogin($browser);
    $host = $this->getHost($URL);
    $browser->setCookie('Login', $cookie, $host);

    /* at this point the test is ready to naviate to the url is wants to
     * test and starts testing.
     */

    $this->assertTrue(is_dir('/tmp'), "this is true");
  }
}

?>
