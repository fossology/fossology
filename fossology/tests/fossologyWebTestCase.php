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
require_once SIMPLE_TEST . 'web_tester.php';

/* does the path need to be modified?, I don't recommend running the
 * ../copy of the program to test.  I think the test should define/create
 * it when doing setup.
 */

class fossologyWebTestCase extends WebTestCase
{

  protected $url;
  protected $user;
  protected $password;

/*
  function __construct($url, $user, $password)
  {

    if (is_null($url))
    {
      $this->url = 'http://localhost/';
    } else
    {
      $this->url = $url;
    }
    if (is_null($user))
    {
      $this->user = 'fossy';
    } else
    {
      $this->user = $user;
    }
    if (is_null($password))
    {
      $this->password = 'fossy';
    } else
    {
      $this->password = $url;
    }
  }
  */

  public function repoLogin($browser = NULL, $user = 'fossy', $password = 'fossy')
  {
    $page = NULL;
    $cookieValue = NULL;

    $this->useProxy('http://web-proxy.fc.hp.com:8088', 'web-proxy', '');
    if (is_null($browser))
    {
      $browser = & new SimpleBrowser();
    }
    $this->assertTrue(is_object($browser));
    $browser->useCookies();
    $cookieValue = $browser->getCookieValue('osrb-1.fc.hp.com', '/', 'Login');
    // need to check $cookieValue for validity
    $browser->setCookie('Login', $cookieValue, 'osrb-1.fc.hp.com');
    $this->assertTrue($browser->get('http://osrb-1.fc.hp.com/repo/?mod=auth&nopopup=1'));
    $this->assertTrue($browser->setField('username', $user));
    $this->assertTrue($browser->setField('password', $password));
    $this->assertTrue($browser->isSubmit('Login'));
    $this->assertTrue($browser->clickSubmit('Login'));
    $page = $browser->getContent();
    preg_match('/User Logged In/', $page, $matches);
    $this->assertTrue($matches, "Login PASSED");
    $browser->setCookie('Login', $cv, 'osrb-1.fc.hp.com');
    $page = $browser->getContent();
    $NumMatches = preg_match('/User Logged Out/', $page, $matches);
    $this->assertFalse($NumMatches, "User Logged out!, Login Failed! %s");
    return($cookieValue);
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
    $NumMatches = preg_match($pattern, $page, $matches);
    //print "*** assertText: matches is:***\n";
    //$this->dump($matches);
    if(NumMatches)
    {
      return(TRUE);
    }
    return(FALSE);
  }

  public function getUrl()
  {
    return $this->$url;
  }
  public function getUser()
  {
    return $this->$user;
  }
  public function getPassword()
  {
    return $this->$password;
  }
}
?>
