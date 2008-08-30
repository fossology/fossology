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
 * fossTestCases
 *
 * This is the base class for fossology tests.
 *
 * There are utility functions in this class for general use.
 *
 * @package fossology
 * @subpackage fossTestCases
 *
  * @version "$Id:  $"
 *
 * Created on Jul 29, 2008
 */

require_once ('TestEnvironment.php');

global $URL;
global $USER;
global $PASSWORD;

abstract class fossTestCases extends WebTestCase{
  public $mybrowser;
  public $cookie;
  public $debug;
  private $Url;
  private $User;
  private $Password;

  abstract function myassertText($page, $pattern);
  abstract function setAgents($agents = NULL);
  abstract function repoLogin($browser = NULL, $user, $password);
  abstract function createAFolder($parent, $name, $description = null);
  //abstract function rLogin($browser = NULL, $user = 'fossy',$password = 'fossy');
  public function getBrowser() { return($this->mybrowser); }
  public function setBrowser($browser) { return($this->mybrowser = $browser); }
  public function getCookie() { return($this->cookie); }
  public function setCookie($cookie) { return($this->cookie = $cookie); }
  /*
  public function mygetUrl() { return($this-> $Url); }
  private function getUser()
  {
    return $this-> $User;
  }
  private function getPassword()
  {
    return $this-> $Password;
  }
  */
}

abstract class testUtils extends fossTestCases
{
  public $mybrowser;
  public $debug;
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
  public function myassertText($page, $pattern)
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
      return NULL;       // No agents to set
    }
    /* see them all if 'all' */
    if (0 === strcasecmp($agents, 'all'))
    {
      foreach ($agentList as $agent => $name)
      {
        if($this->debug)
        {
          print "SA: setting agents for 'all', agent name is:$name\n";
        }

        $this->assertTrue($this->mybrowser->setField($name, 1));
      }
      return(TRUE);
    }
    /*
     * what is left is 0 or more numbers, comma seperated
     * parse them then use them to set a list of agents.
     */
    $numberList = explode(',', $agents);
    $numAgents = count($numberList);

    if ($numAgents = 0)
    {
      return NULL;       // no agents to schedule
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

      if($this->debug == 1) { print "the agent list is:\n"; }

      foreach($checklist as $agent)
      {
        if($this->debug)
        {
          print "DEBUG: $agent\n";
        }
        $this->assertTrue($this->mybrowser->setField($agent, 1));
      }
    }
    return(TRUE);
  } //setAgents

// old end testUtils}

/*
    public function createAFolder($parent, $name, $description = null)
  {
    print "createFolder is running\n";
    print "createFolder:Parameters: P:$parent N:$name D:$description\n";


    if (is_null($description)) // set default if null
    {
      $description = "Folder created by testFolder as subfolder of $parent";
    }
    print "caf DAM\n";
    $urlNow = $this->mybrowser->getUrl();
    $page = $this->mybrowser->get($urlNow);
    $this->assertTrue($this->assertText($page,'/Create a new Fossology folder/'));

    $FolderId = $this->getFolderId($parent, $page);
    $this->assertTrue($this->mybrowser->setField('parentid', $FolderId));
    $this->assertTrue($this->mybrowser->setField('newname', $name));
    $this->assertTrue($this->mybrowser->setField('description', "$description"));
    $page = $this->mybrowser->clickSubmit('Create!');
    $this->assertTrue(page);
    $this->assertTrue($this->assertText($page, "/Folder $name Created/"),
     "FAIL! Folder $name Created not found\n");
  }


abstract class genUtils extends testUtils
{
*/
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
   * Note, this function is not very useful on inspection... consider
   * rewrite or scrap it.
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
// end genclass }
}           // new testUtils (end of that is :)
?>
