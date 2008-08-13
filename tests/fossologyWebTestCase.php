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
require_once ('../../../../tests/TestEnvironment.php');

global $URL;
global $USER;
global $PASSWORD;

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
    global $URL;
    global $USER;
    global $PASSWORD;
    $page = NULL;
    $cookieValue = NULL;

    if (is_null($browser))
    {
      $browser = & new SimpleBrowser();
    }
    $host = $this->getHost($URL);
    $this->assertTrue(is_object($browser));
    $browser->useCookies();
    $cookieValue = $browser->getCookieValue($host, '/', 'Login');
    // need to check $cookieValue for validity
    $browser->setCookie('Login', $cookieValue, $host);
    $this->assertTrue($browser->get("$URL?mod=auth&nopopup=1"));
    $this->assertTrue($browser->setField('username', $user));
    $this->assertTrue($browser->setField('password', $password));
    $this->assertTrue($browser->isSubmit('Login'));
    $this->assertTrue($browser->clickSubmit('Login'));
    $page = $browser->getContent();
    preg_match('/User Logged In/', $page, $matches);
    $this->assertTrue($matches, "Login PASSED");
    $browser->setCookie('Login', $cookieValue, $host);
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
    //print "*** assertText: NumMatches is:$NumMatches\nmatches is:***\n";
    //$this->dump($matches);
    if($NumMatches)
    {
      return(TRUE);
    }
    return(FALSE);
  }

  /**
   * public function getHost
   *
   * @param string $URL a url in the form of http://somehost.xx.com/repo/
   *
   * @return string $host he somehost.xx.com part is returned
   *
   * @TODO generalize so you don't depend on /repo/
   */
  public function getHost($URL)
  {
    if(empty($URL))
    {
      return('localhost');
    }
    $temp = rtrim($URL, '/repo/\n');
    $host = ltrim($temp, 'http://');
    //print "DB GHost: host is:$host\n";
    return($host);
  }

  /**
   * parse the folder id out of the html...
   *
   * @TODO see if you can somehow use the ui routine instead!  much
   * better.... (talk with neal as don't want to have to be a class of
   * that type...?')
   */
  public function getFolderId($folderName, $page)
  {
    // this function doesn't work!
    /* no error checks for now, may use the ui */
    $found = preg_match("/.*value='([0-9].*?)'.*?;($folderName)<\//", $page, $matches);
    //print "DB: matches is:\n";
    //var_dump($matches) . "\n";
    return($matches[1]);
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
