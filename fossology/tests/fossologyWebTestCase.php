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

// FIX THIS PATH REQUIRE STUFF BELOW!

if (!defined('SIMPLE_TEST'))
  define('SIMPLE_TEST', '/usr/share/php/simpletest/');

/* simpletest includes */
require_once SIMPLE_TEST . 'unit_tester.php';
require_once SIMPLE_TEST . 'reporter.php';
require_once SIMPLE_TEST . 'web_tester.php';
require_once ('TestEnvironment.php');
//require_once ('testClasses/common.php');

global $URL;
global $USER;
global $PASSWORD;

class fossologyWebTestCase extends WebTestCase
{
  public $mybrowser;
  public $debug;

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
    return ($cookieValue);
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
    if ($NumMatches)
    {
      return (TRUE);
    }
    return (FALSE);
  }

  /**
  * function uploadAFile
  * ($parentFolder,$uploadFile,$description=null,$uploadName=null,$agents=null)
  *
  * Upload a file and optionally schedule the agents.
  *
  * @param string $parentFolder the parent folder name, default is root
  * folder (1)
  * @param string $uploadFile the path to the file to upload
  * @param string $description=null optonal description
  * @param string $uploadName=null optional upload name
  *
  * @todo, add in selecting agents the parameter to this routine will
  * need to be quoted if it contains commas.
  *
  * @todo add ability to specify uploadName
  *
  * @return false on error
  */
  public function uploadAFile($parentFolder, $uploadFile, $description = null, $uploadName = null, $agents = null)
  {
    global $URL;
    /*
     * check parameters:
     * default parent folder is root folder
     * no uploadfile return false
     * description and upload name are optonal
     * future: agents are optional
     */
    if (empty ($parentFolder))
    {
      $parentFolder = 1;
    }
    if (empty ($uploadFile))
    {
      return (FALSE);
    }
    if (is_null($description)) // set default if null
    {
      $description = "File $uploadFile uploaded by test UploadAFileTest";
    }
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->assertText($loggedIn, '/Upload/'));
    $page = $this->mybrowser->get("$URL?mod=upload_file");
    $this->assertTrue($this->assertText($page, '/Upload a New File/'));
    $this->assertTrue($this->assertText($page, '/Select the file to upload:/'));
    $this->assertTrue($this->mybrowser->setField('folder', $parentFolder), "FAIL! could not select Parent Folder!\n");
    $this->assertTrue($this->mybrowser->setField('getfile', "$uploadFile"));
    $this->assertTrue($this->mybrowser->setField('description', "$description"));
    /*
     * the test breaks if the name is set to null $this->assertTrue
     * ($this- >mybrowser- >setField ('name', $upload_name));
     *
     */
    /* we won't select any agents for now.... see todo above */
    $page = $this->mybrowser->clickSubmit('Upload!');
    $this->assertTrue(page);
    //print "************* page after Upload! is *************\n$page\n";
    $this->assertTrue($this->assertText($page, '/Upload added to job queue/'));
  }
  /**
  * function uploadAUrl
  * ($parentFolder,$uploadFile,$description=null,$uploadName=null,$agents=null)
  *
  * Upload a file and optionally schedule the agents.
  *
  * @param string $parentFolder the parent folder name, default is root
  * folder (1)
  * @param string $uploadFile the path to the file to upload
  * @param string $description=null optonal description
  * @param string $uploadName=null optional upload name
  * @param string $agents=null optional agents to schedule
  *
  * @todo, add in selecting agents the parameter to this routine will
  * need to be quoted if it contains commas.
  *
  * @return false on error
  */
  public function uploadAUrl($parentFolder = 1, $url, $description = null, $uploadName = null, $agents = null)
  {
    global $URL;
    /*
     * check parameters:
     * default parent folder is root folder
     * no uploadfile return false
     * description and upload name are optonal
     * future: agents are optional
     */
    if (empty ($parentFolder))
    {
      $parentFolder = 1;
    }
    if (empty ($uploadFile))
    {
      return (FALSE);
    }
    if (is_null($description)) // set default if null
    {
      $description = "File $uploadFile uploaded by test UploadAUrl";
    }
    print "starting UploadAUrlTest\n";
    $this->useProxy('http://web-proxy.fc.hp.com:8088', 'web-proxy', '');
    $browser = & new SimpleBrowser();
    $page = $browser->get($URL);
    $this->assertTrue($page);
    $this->assertTrue(is_object($browser));
    $cookie = $this->repoLogin($browser);
    $host = $this->getHost($URL);
    $browser->setCookie('Login', $cookie, $host);

    $loggedIn = $browser->get($URL);
    $this->assertTrue($this->assertText($loggedIn, '/Upload/'));
    $this->assertTrue($this->assertText($loggedIn, '/From URL/'));
    $page = $browser->get("$URL?mod=upload_url");
    $this->assertTrue($this->assertText($page, '/Upload from URL/'));
    $this->assertTrue($this->assertText($page, '/Enter the URL to the file:/'));
    /* only look for the the folder id if it's not the root folder */
    if ($parentFolder != 1)
    {
      $FolderId = $this->getFolderId($parentFolder, $page);
    }
    $this->assertTrue($browser->setField('folder', $FolderId));
    $this->assertTrue($browser->setField('geturl', $url));
    $this->assertTrue($browser->setField('description', "$description"));
    /* Set the name field if an upload name was passed in. */
    if (!(is_null($upload_name)))
    {
      $this->assertTrue($browser->setField('name', $upload_name));
    }
    /* we won't select any agents this time' */
    $page = $browser->clickSubmit('Upload!');
    $this->assertTrue(page);
    $this->assertTrue($this->assertText($page, '/Upload added to job queue/'));

    //print  "************ page after Upload! *************\n$page\n";
  }
  /**
   * function setAgents
   *
   * Set 0 or more agents
   *
   * Assumes it is on a page where agents can be selected with
   * checkboxes.  Will produce test errors if it is not.
   *
   * @param string $agents a comma seperated list of number 1-4 or all.
   * e.g. 1 1,2 1,4 4,3 all
   *
   */
  public function setAgents($agents = NULL)
  {
    $agentList = array (
      'license' => 'Check_agent_license',
      'mimetype' => 'Check_agent_mimetype',
      'pkgmetagetta' => 'Check_agent_pkgmetagetta',
      'specagent' => 'Check_agent_specagent',

    );
    /* check parameters and parse */
    if (is_null($agents))
    {
      return;       // No agents to set
    }
    /* see them all if 'all' */
    if (0 === strcasecmp($agents, 'all'))
    {
      foreach ($agentList as $agent => $name)
      {
        $this->assertTrue($browser->setField($name, 1));
      }
    }
    /*
     * what is left is 0 or more numbers, comma seperated
     * parse them then use them to set a list of agents.
		 */
    $numberList = explode(',', $agents);
    $numAgents = count($numberList);
    if ($numAgents = 0)
    {
      return;       // no agents to schedule
    }
    else
    {
      foreach ($numberList as $number)
      {
        switch ($number)
        {
          case 1 :
            $checklist[] = $agentList['license'];
            break;
          case 2 :
            $checklist[] = $agentList['mimetype'];
            break;
          case 3 :
            $checklist[] = $agentList['pkgmetagetta'];
            break;
          case 4 :
            $checklist[] = $agentList['specagent'];
            break;
        }
      } // foreach
      if($this->debug) { print "the agent list is:\n"; }


      foreach($checklist as $agent)
      {
        if($this->debug)
        {
          print "DEBUG: $agent\n";
        }
        $this->assertTrue($browser->setField($agent, 1));
      }
    }
  } //setAgents

  /********************************************************************
    * Static functions
    *******************************************************************/
  /**
   * public function getHost
   *
   * returns the host (if present) from a URL
   *
   * @param string $URL a url in the form of http://somehost.xx.com/repo/
   *
   * @return string $host the somehost.xxx part is returned or
   *         NULL, if there is no host in the uri
   *
   */

  public function getHost($URL)
  {
    if (empty ($URL))
    {
      return (NULL);
    }
    return (parse_url($URL, PHP_URL_HOST)); // can return NULL
  }

  /**
   * parse the folder id out of the html...
   *
   *@param string $folderName the name of the folder
   *@param string $page the xhtml page to search
   *
   *@return string (the folder id)
   */
  public function getFolderId($folderName, $page)
  {
    $found = preg_match("/.*value='([0-9].*?)'.*?;($folderName)<\//", $page, $matches);
    //print "DB: matches is:\n";
    //var_dump($matches) . "\n";
    return ($matches[1]);
  }

  /**
   * getBrowserUri get the url fragment to display the upload from the
   * xhtml page.
   *
   * @param string $name the name of a folder or upload
   * @param string $page the xhtml page to search
   *
   * @return $string the matching uri or null.
   *
   */
  public function getBrowseUri($name, $page)
  {
    //print "DB: GBURI: page is:\n$page\n";
    //$found = preg_match("/href='(.*?)'>($uploadName)<\/a>/", $page, $matches);
    // doesn't work: '$found = preg_match("/href='(.*?)'>$name/", $page, $matches);
    $found = preg_match("/href='((.*?)&show=detail).*?/", $page, $matches);
    //$found = preg_match("/ class=.*?href='(.*?)'>$name/", $page, $matches);
    print "DB: GBURI: found matches is:$found\n";
    print "DB: GBURI: matches is:\n";
    var_dump($matches) . "\n";
    if ($found)
    {
      return ($matches[1]);
    } else
    {
      return (NULL);
    }
  }
  /**
   * getNextLink given a pattern, find the link in the page and return
   * it.
   *
   * @param string $pattern a preg_match compatible pattern
   * @param string $page    the xhtml page to search
   *
   * @return string $result or null if no pattern found.
   *
   */
  public function getNextLink($pattern, $page, $debug = 0)
  {
    $found = preg_match($pattern, $page, $matches);
    if ($debug)
    {
      print "DB: GNL: pattern is:$pattern\n";
      print "DB: GNL: found matches is:$found\n";
      print "DB: GNL: matches is:\n";
      var_dump($matches) . "\n";
    }
    if ($found)
    {
      return ($matches[1]);
    } else
    {
      return (NULL);
    }
  }

  /**
   * function makeUrl
   * Make a url from the host and query strings.
   *
   * @param $string $host the host (e.g. somehost.com, host.privatenet)
   * @param $string $query the query to append to the host.
   *
   * @return the http string or NULL on error
   */
  public function makeUrl($host, $query)
  {
    if (empty ($host))
    {
      return (NULL);
    }
    if (empty ($query))
    {
      return (NULL);
    }
    return ("http://$host$query");
  }

  public function getUrl()
  {
    return $this-> $url;
  }
  public function getUser()
  {
    return $this-> $user;
  }
  public function getPassword()
  {
    return $this-> $password;
  }
}
?>
