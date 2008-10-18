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
 * fossologyTest
 *
 * This is the base class for fossology tests.  All fossologyTestCases
 * extend this class.  A test could extend this class, but would not
 * have access to all the methods in fossologyTestCases.
 *
 * Only put methods in here that more than one fossologyTestCase use.
 *
 * @version "$Id$"
 *
 * Created on Sept. 1, 2008
 */
/**#@+
 * include test files
 */
require_once ('TestEnvironment.php');
require_once ('commoneTestFuncs.php');
/**#@-*/

global $URL;
global $USER;
global $PASSWORD;

class fossologyTest extends WebTestCase
{
  public $mybrowser;
  public $cookie;
  public $debug;
  private $Url;
  private $User;
  private $Password;

  /* Accesor methods */
  public function getBrowser()
  {
    return ($this->mybrowser);
  }
  public function setBrowser($browser)
  {
    return ($this->mybrowser = $browser);
  }
  public function getCookie()
  {
    return ($this->cookie);
  }
  public function setmyCookie($cookie)
  {
    return ($this->cookie = $cookie);
  }



  /* Factory methods, still need to change methods */
  function pmm($test)
  {
    return(new parseMiniMenu($this));
  }
  function plt($test)
  {
    return(new parseLicenseTbl($this));
  }

  /* Methods */

  /**
   * createTestingFolder
   *
   * Create a folder for use in testing
   *
   * @param string $name the name of the folder to create
   * @param string $parent the name of the parent folder.  This is an
   * optoinal parameter, if none supplied, then the root folder is used.
   *
   * @return boolean
   */
   public function createTestFolder($name, $parent='root')
   {
    global $URL;
    if ($parent == 'root') { $parent = null; }
    if (empty($name)){
      $pid = getmypid();
      $name = 'Testing-' . $pid;
    }
    $page = $this->mybrowser->get($URL);
    $this->createFolder($parent, $name, null);
   }

   /**
   * getFolderId($folderNmae, $page)
   *
   * parse the folder id out of the html...
   *
   *@param string $folderName the name of the folder
   *@param string $page the xhtml page to search
   *
   *@return string (the folder id)
   *
   *@todo check for empty and return null?
   */
  public function getFolderId($folderName, $page)
  {
    /*
     * special case the folder called root, it's always folder id 1.
     * This way we still don't have to query for the name.
     *
     * This will probably break when users are implimented.
     */
    if($folderName == 'root') { return(1); }
    $efolderName = $this->escapeDots($folderName);
    $found = preg_match("/.*value='([0-9].+)'.*?;($efolderName)<\//", $page, $matches);
    //print "GFID: matches is:\n";
    //var_dump($matches) . "\n";
    return ($matches[1]);
  }
  /**
   * getUploadId($uploadName, $page)
   *
   * parse the folder id out of the html in $page
   *
   *@param string $uploadName the name of the upload
   *@param string $page the xhtml page to search
   *
   *@return string (upload ID)
   *
   *@todo check for empty and return null?
   */
  public function getUploadId($uploadName, $page)
  {
    $euploadName = $this->escapeDots($uploadName);
    $found = preg_match("/.*?value='([0-9].*?)'>($euploadName ).*?</", $page, $matches);
    //print "GUID: matches is:\n";
    //var_dump($matches) . "\n";
    return ($matches[1]);
  }


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
      return NULL; // No agents to set
    }
    /* see them all if 'all' */
    if (0 === strcasecmp($agents, 'all'))
    {
      foreach ($agentList as $agent => $name)
      {
        if ($this->debug)
        {
          print "SA: setting agents for 'all', agent name is:$name\n";
        }

        $this->assertTrue($this->mybrowser->setField($name, 1));
      }
      return (TRUE);
    }
    /*
     * what is left is 0 or more numbers, comma seperated
     * parse them then use them to set a list of agents.
     */
    $numberList = explode(',', $agents);
    $numAgents = count($numberList);

    if ($numAgents = 0)
    {
      return NULL; // no agents to schedule
    } else
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

      if ($this->debug == 1)
      {
        print "the agent list is:\n";
      }

      foreach ($checklist as $agent)
      {
        if ($this->debug)
        {
          print "DEBUG: $agent\n";
        }
        $this->assertTrue($this->mybrowser->setField($agent, 1));
      }
    }
    return (TRUE);
  } //setAgents

  /**
   * Login()
   *
   * Login to the FOSSology Repository, uses the globals set in
   * TestEnvironment.php
   *
   */
  public function Login()
  {
    global $URL;
    $browser = & new SimpleBrowser();
    $this->setBrowser($browser);
    $this->assertTrue(is_object($this->mybrowser), "FAIL! Login() internal failure did not get a browser object\n");
    $page = $this->mybrowser->get($URL);
    $this->assertTrue($page,"Login FAILED! did not fetch a web page, can't login\n'");
    $cookie = $this->_repoDBlogin($this->mybrowser);
    $this->setmyCookie($cookie);
    $host = $this->getHost($URL);
    $this->mybrowser->setCookie('Login', $cookie, $host);
    $url = $this->mybrowser->getUrl();
    $page = $this->mybrowser->getContent($URL);
  }

  private function _repoDBlogin($browser = NULL)
  {

    //print "repoLogin is running\n";
    if (is_null($browser))
    {
      //print "_repoDBlogin setting browser\n";
      $browser = & new SimpleBrowser();
    }
    $this->setBrowser($browser);
    global $URL;
    global $USER;
    global $PASSWORD;
    $page = NULL;
    $cookieValue = NULL;

    $host = $this->getHost($URL);
    $this->assertTrue(is_object($this->mybrowser));
    $this->mybrowser->useCookies();
    $cookieValue = $this->mybrowser->getCookieValue($host, '/', 'Login');
    // need to check $cookieValue for validity
    $this->mybrowser->setCookie('Login', $cookieValue, $host);
    $page = $this->mybrowser->get($URL);
    $this->assertTrue($this->mybrowser->get("$URL?mod=auth&nopopup=1"));
    $this->assertTrue($this->mybrowser->setField('username', $USER));
    $this->assertTrue($this->mybrowser->setField('password', $PASSWORD));
    $this->assertTrue($this->mybrowser->isSubmit('Login'));
    $this->assertTrue($this->mybrowser->clickSubmit('Login'));
    $page = $this->mybrowser->getContent();
    preg_match('/User Logged In/', $page, $matches);
    $this->assertTrue($matches, "Failure! Login FAILED, did not see 'User Logged In'\n");
    $this->mybrowser->setCookie('Login', $cookieValue, $host);
    $page = $this->mybrowser->getContent();
    $NumMatches = preg_match('/User Logged Out/', $page, $matches);
    $this->assertFalse($NumMatches, "User Logged out!, Login Failed! %s");
    return ($cookieValue);
  }
} // fossolgyTest
?>
