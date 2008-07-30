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
 * WebTest case for fossology
 *
 * This is the base class for fossology unit tests.  All tests should
 * require_once this class and then extend it.
 *
 * There are utility functions in this class for general use.
 *
 * This class defines where simpletest is and includes the modules
 * needed.
 *
 * @package fossology
 * @subpackage tests
 *
  * @version "$Id: $"
 *
 * Created on Jul 21, 2008
 */

//require_once('fossologyUnitTestCase.php');

if (!defined('SIMPLE_TEST'))
  define('SIMPLE_TEST', '/usr/share/php/simpletest/');

/* simpletest includes */
require_once SIMPLE_TEST . 'unit_tester.php';
require_once SIMPLE_TEST . 'reporter.php';
require_once SIMPLE_TEST . 'mock_objects.php';
require_once SIMPLE_TEST . 'web_tester.php';

/* does the path need to be modified?, I don't recommend running the
 * ../copy of the program to test.  I think the test should define/create
 * it when doing setup.
 */

class fossologyWebTestCase extends WebTestCase
{

  public function repoLogin($browser = NULL, $user = 'fossy', $password = 'fossy')
  {
    $page = 0;
    print "in repoLogin\n";
    $this->useProxy('http://web-proxy.fc.hp.com:8088', 'web-proxy', '');
    if (is_null($browser))
    {
      $browser = & new SimpleBrowser();
    }
    //print "********* checking object passed in**************\n";
    $this->assertTrue(is_object($browser));
    $browser->useCookies();
    $page = $browser->get('http://osrb-1.fc.hp.com/repo/');
    $this->assertTrue($page);
    $this->assertTrue($browser->get('http://osrb-1.fc.hp.com/~markd/ui-md/?mod=auth&nopopup=1'));
    $this->assertTrue($browser->setField('username', $user));
    $this->assertTrue($browser->setField('password', $password));
    $this->assertTrue($browser->isSubmit('Login'));
    $this->assertTrue($browser->clickSubmit('Login'));
    $page = $browser->getContent();
    preg_match('/User Logged In/', $page, $matches);
    $this->assertTrue($matches);
    // retry is needed for some reason or we just stay on the login page
    $c = $_COOKIE['Login'];
    echo "Login cookie is:$c\n";
    $this->assertTrue($browser->retry());
    $mysid = session_id();
    print "RepoLogin SID IS:$mysid\n";
    print "RepoLogin trying ses[Login]\n";
    var_dump($_SESSION['Login']);
    print "RepoLogin trying ses[name]\n";
    var_dump($_SESSION['name']);
    //$this->dump($mycookie);
    $page = $browser->getContent();
    //print "************ After Login/ReTry page is:********************\n";
    //$this->dump($page);
  }

/**
 * function assertText
 *
 * @param string $page, a page of html or text to search
 * @param string $pattern a perl/php pattern e.g. '/suff/'
 *
 * @return boolean
 * @access public
 *
 */
  public function assertText($page, $pattern)
  {
    preg_match($pattern, $page, $matches);
    //print "*** assertText: matches is:***\n";
    //$this->dump($matches);
    if(count($matches))
    {
      return(TRUE);
    }
    return(FALSE);
  }
}
?>
