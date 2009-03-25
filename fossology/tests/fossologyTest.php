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
require_once ('commonTestFuncs.php');
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
  private $User = NULL;
  private $Password = NULL;

  /* Accesor methods */
  public function getBrowser() {
    return ($this->mybrowser);
  }
  public function getCookie() {
    return ($this->cookie);
  }
  public function getPassword() {
    return ($this->Password);
  }
  public function getUser() {
    return ($this->User);
  }
  public function setBrowser($browser) {
    return ($this->mybrowser = $browser);
  }
  public function setmyCookie($cookie) {
    return ($this->cookie = $cookie);
  }
  public function setPassword($password) {
    return ($this->Password = $password);
  }
  public function setUser($user) {
    return ($this->User = $user);
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
  }// createTestingFolder
  /**
  * getFolderId
  *
  * parse the folder id out of the select statement
  *
  *@param string $folderName the name of the folder
  *@param string $page the xhtml page to search
  *@param string $selectName the name attribute of the select statement to
  *parse
  *
  *@return int $FolderId, NULL on error
  *
  */
  public function getFolderId($folderName, $page, $selectName) {
    if(empty($folderName)) {
      return(NULL);
    }
    else if (empty($page)) {
      return(NULL);
    }
    else if (empty($selectName)) {
      return(NULL);
    }
    /*
     * special case the folder called root, it's always folder id 1.
     * This way we still don't have to query for the name.
     *
     * This will probably break when users are implimented.
     */
    if(($folderName == 'root') || ($folderName == 1)) {
      return(1);
    }
    $FolderId = $this->parseSelectStmnt($page, $selectName, $folderName);
    //print "GFID: folderId is:$FolderId\n";
    if(empty($FolderId)) {
      return(NULL);
    }
    return($FolderId);
  }
  /**
   * getUploadId($uploadName, $page, $selectName)
   *
   * parse the folder id out of the select in the $page
   *
   *@param string $uploadName the name of the upload
   *@param string $page the xhtml page to search
   *@param string $selectName the name attribute of the select statement to
   *parse
   *
   *@return int $uploadId or NULL on errro
   *
   */
  public function getUploadId($uploadName, $page, $selectName) {
    if(empty($uploadName)) {
      return(NULL);
    }
    else if (empty($page)) {
      return(NULL);
    }
    else if (empty($selectName)) {
      return(NULL);
    }
    print "GUPID: selname is:$selectName\nuploadName is:$uploadName\n";
    $UploadId = $this->parseSelectStmnt($page, $selectName, $uploadName);
    if(empty($UploadId)) {
      return(NULL);
    }
    return ($UploadId);
  }

  public function myassertText($page, $pattern) {
    $NumMatches = preg_match($pattern, $page, $matches);
    //print "*** assertText: NumMatches is:$NumMatches\nmatches is:***\n";
    //$this->dump($matches);
    if ($NumMatches) {
      return (TRUE);
    }
    return (FALSE);
  }

  /**
   * parseSelectStmnt
   *
   * Parse the specified select statement on the page
   *
   * @param string $page the page to search
   * @param string $selectName the name of the select
   * @param string $optionText the text of the option statement
   * @return mixed $select either array (if first two args present) or int if
   * all three arguments present. NULL on error.
   *
   * Format of the array returned:
   *
   * array returned: Array=>[select-name-attribute]=>Array[option text]=>
   *                                                      [option value attribute]
   */
  public function parseSelectStmnt($page,$selectName,$optionText=NULL) {
    if(empty($page)) {
      return(NULL);
    }
    if(empty($selectName)) {
      return(NULL);
    }
    $hpage = new DOMDocument();
    //@$hpage->loadHTMLFile("/home/markd/deluser.html");
    @$hpage->loadHTML($page);
    /* get select and options */
    $selectList = $hpage->getElementsByTagName('select');
    $optionList = $hpage->getElementsByTagName('option');
    print "number of selects on this page:$selectList->length\n";
    print "number of options on this page:$optionList->length\n";

    /*
     * gather the section names and group the options with each section
     * collect the data at the same time.  Assemble into the data structure.
     */
    for($i=0; $i < $selectList->length; $i++) {
      $ChildList = $selectList->item($i)->childNodes;
      foreach($ChildList as $child) {
        $optionValue = $child->getAttribute('value');
        $orig = $child->nodeValue;
        /* need to clean up the string, to get rid of &nbsp codes */
        $he = htmlentities($orig);
        $htmlGone = preg_replace('/&.*?;/','',$he);
        $cleanText = trim($htmlGone);
        if(!empty($optionText)) {
          $noDotOptText = escapeDots($optionText);
          $match = preg_match("/^$noDotOptText/", $cleanText, $matches);
          if($match) {
            //print "Adding matches[0] to select array\n";
            $Selects[$selectList->item($i)->getAttribute('name')][$matches[0]] = $optionValue;
          }
        }
        else {
          //print "Adding cleanText to select array\n";
          $Selects[$selectList->item($i)->getAttribute('name')][$cleanText] = $optionValue;
        }
      }
      /*
       //print "number of options on this page:$optionList->length\n";
       ///make sure parent node is select and matches $selectName
       $pn = $optionList->item($Found)->parentNode->nodeName;
       $sname = $selectList->item($Found)->getAttribute('name');
       //print "PSS: optionText is:$optionText\n";
       if($optionList->item($Found)->parentNode->nodeName == 'select') {
       //print "PSS: parent node is select\n";
       $name = $selectList->item($Found)->getAttribute('name');
       //print "PSS: node name attribute is:$name\n";
       if($selectList->item($Found)->getAttribute('name') == $selectName){

       * build an array with the select name as the key to an array of
       * key=>value pairs, where the key is the text of the select and
       * the value is the value attribute.

       print "PSS: creating select array (matched $selectName)\n";
       $select = array();
       //print "PSS: optionText is:$optionText\n";
       print "PSS: options length is:$optionList->length\n";
       $start = $optionList->length - $optionStart;
       print "PSS: starting at $start for options loop\n";
       for($i=$start; $i< $optionList->length; $i++) {
       $optionValue = $optionList->item($i)->getAttribute('value');
       $orig = $optionList->item($i)->nodeValue;
       /* need to clean up the string, to get rid of &nbsp codes
       $he = htmlentities($orig);
       $htmlGone = preg_replace('/&.*?;/','',$he);
       //$nodots = escapeDots($htmlGone);
       $cleanText = trim($htmlGone);
       if(!empty($optionText)) {
       $noDotOptText = escapeDots($optionText);
       $match = preg_match("/^$noDotOptText/", $cleanText, $matches);
       if($match) {
       //print "Adding matches[0] to select array\n";
       $select[$selectName][$matches[0]] = $optionValue;
       }
       }
       else {
       //print "Adding cleanText to select array\n";
       $select[$selectName][$cleanText] = $optionValue;
       }
       }
       }
       }
       */
    }
    /* Return either an int, or an array */
    print "PSS: Selects is:\n"; print_r($Selects) . "\n";
    if (!is_null($optionText)){
      if(array_key_exists($optionText,$Selects[$selectName])){
        return($Selects[$selectName][$optionText]);
      }
      else {
        return(NULL);
      }
    }
    else {
      if(array_key_exists($selectName,$Selects)){
        return($Selects[$selectName]);
      }
      else {
        return(NULL);     // didn't find any...
      }
    }
  }  // parseSelectStmnt

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
   * @return NULL, or string on error
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
      return (NULL);
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
    return (NULL);
  } //setAgents

  /**
   * Login to the FOSSology Repository, uses the globals set in
   * TestEnvironment.php as the default or the user and password supplied.
   *
   * @param string $User the fossology user name
   * @param string $Password the fossology user password
   *
   */
  public function Login($User=NULL, $Password=NULL)
  {
    global $URL;

    if(!empty($User)) {
      $this->setUser($User);
    }
    if(!empty($Password)) {
      $this->setPassword($Password);
    }
    $browser = & new SimpleBrowser();
    $this->setBrowser($browser);
    $this->assertTrue(is_object($this->mybrowser),
      "FAIL! Login() internal failure did not get a browser object\n");
    $page = $this->mybrowser->get($URL);
    $this->assertTrue($page,"Login FAILED! did not fetch a web page, can't login\n'");
    $cookie = $this->_repoDBlogin($this->mybrowser);
    $this->setmyCookie($cookie);
    $host = getHost($URL);
    $this->mybrowser->setCookie('Login', $cookie, $host);
    $url = $this->mybrowser->getUrl();
    $page = $this->mybrowser->getContent($URL);
  }
  /**
   * Logout of the FOSSology Repository, uses the globals set in
   * TestEnvironment.php as the default or the user and password supplied.
   *
   * @param string $User the fossology user name
   * @param string $Password the fossology user password
   *
   */
  public function Logout($User=NULL)
  {
    global $URL;
    global $USER;

    if(!empty($User)) {
      $this->setUser($User);
    }
    else {
      $this->setUser($USER);
    }
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, "/User:<\/small> $User/"),
      "Did not find User:<\/small> $User");
    $page = $this->mybrowser->get("$URL?mod=auth");
    $this->assertTrue($this->myassertText($page, "/User Logged Out/"));
    $this->setUser(NULL);
    $this->setPassword(NULL);
  }

  private function _repoDBlogin($browser = NULL)
  {

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

    $host = getHost($URL);
    $this->assertTrue(is_object($this->mybrowser));
    $this->mybrowser->useCookies();
    $cookieValue = $this->mybrowser->getCookieValue($host, '/', 'Login');
    // need to check $cookieValue for validity
    $this->mybrowser->setCookie('Login', $cookieValue, $host);
    $page = $this->mybrowser->get($URL);
    $this->assertTrue($this->mybrowser->get("$URL?mod=auth&nopopup=1"));
    /* Use the test configured user if none specified */
    if(empty($this->User)) {
      $this->setUser($USER);
    }
    if(empty($this->Password)) {
      $this->setPassword($PASSWORD);
    }
    $this->assertTrue($this->mybrowser->setField('username', $this->User));
    $this->assertTrue($this->mybrowser->setField('password', $this->Password));
    $this->assertTrue($this->mybrowser->isSubmit('Login'));
    $this->assertTrue($this->mybrowser->clickSubmit('Login'));
    $page = $this->mybrowser->getContent();
    preg_match('/User Logged In/', $page, $matches);
    $this->assertTrue($matches, "Failure! Login FAILED, did not see " .
      "'User Logged In for user $this->User'\n");
    $this->mybrowser->setCookie('Login', $cookieValue, $host);
    $page = $this->mybrowser->getContent();
    $NumMatches = preg_match('/User Logged Out/', $page, $matches);
    $this->assertFalse($NumMatches, "User Logged out!, Login Failed! %s");
    return ($cookieValue);
  }
} // fossolgyTest
?>
