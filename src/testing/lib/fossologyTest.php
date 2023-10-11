<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * fossologyTest
 *
 * This is the base class for fossology tests.  All fossologyTestCases
 * extend this class.  A test could extend this class, but would not
 * have access to all the methods in fossologyTestCases.
 *
 *
 * @package FOSSologyTest
 * @version "$Id: fossologyTest.php 3648 2010-11-08 21:30:35Z rrando $"
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

/**
 * Base clase for fossologyTestCase.  Most FOSSology tests should not extend
 * this class.  Extend fossologyTestCase instead.
 *
 * Only put methods in here that more than one fossologyTestCase can use.
 *
 * @author markd
 */

class fossologyTest extends WebTestCase
{
  public $mybrowser;
  public $cookie;
  public $debug;
  private $Url;
  protected $User = NULL;
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
   * _browser
   *
   * internal method (singleton?) to make sure only one browser per test run
   * is being used.
   *
   * @return resource
   *
   * TODO: fix returns so it's either a resource or ??
   */
  protected function _browser() {

    if(is_object($this->mybrowser)) {
      return($this->mybrowser);
    }
    else {
      $browser = new SimpleBrowser();
      if(is_object($browser)) {
        $this->setBrowser($browser);
      }
      else {
        $this->fail("FAIL! Login() internal failure did not get a browser object\n");
      }
    }
    return($this->mybrowser);
  } //_browser

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
    $UploadId = $this->parseSelectStmnt($page, $selectName, $uploadName);
    if(empty($UploadId)) {
      return(NULL);
    }
    return ($UploadId);
  }


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
    global $USER;
    global $PASSWORD;

    // user name passed in, use what is supplied, (can have blank password)
    if(!empty($User)) {
      $this->setUser($User);
      $this->setPassword($Password);
    }
    else      // no valid paramaters, use user in TestEnvironment.php
    {
      $this->setUser($USER);
      $this->setPassword($PASSWORD);
    }

    $browser = $this->_browser();
    $page = $this->mybrowser->get($URL);
    $this->assertTrue($page,"Login FAILED! did not fetch a web page, can't login\n'");
    $cookie = $this->_repoDBlogin($this->mybrowser);
    $this->setmyCookie($cookie);
    $host = getHost($URL);
    $this->mybrowser->setCookie('Login', $cookie, $host);

    $url = $this->mybrowser->getUrl();
    $page = $this->mybrowser->getContent($url);
    //$c = $this->__chopPage($page);
    //print "*********************page at end of LOGIN is:*******\n$c\n";
  }

  /**
   * Logout of the FOSSology Repository, uses the globals set in
   * TestEnvironment.php as the default or the user and password supplied.
   *
   * @param string $User the fossology user name
   *
   */
  public function Logout($User=NULL)
  {
    global $URL;
    global $USER;

    if(strlen($User)) {
      $this->setUser($User);
    }
    /*
     else {
     $this->setUser($USER);
     }
     */
    $url = $this->mybrowser->getUrl();
    $loggedIn = $this->mybrowser->get($URL);
    //$this->assertTrue($this->myassertText($loggedIn, "/User:<\/small> $User/"),
    //  "Did not find User:<\/small> $User, is $User logged in?\n");
    // must do 2 calls.  For some reason the ?mod=auth does not logout, but
    // gets to a page where the logout link works.


    $page = $this->mybrowser->get("$URL?mod=auth");
    $page = $this->mybrowser->clickLink('logout');
    $host = getHost($URL);
    $clearCookie = $this->mybrowser->setCookie('Login', '', $host);
    $page = $this->mybrowser->get("$URL?mod=Default");
    //$p = $this->__chopPage($page);
    //print "page after logout sequence is:$p\n";

    if($this->myassertText($page,"/This login uses HTTP/") !== TRUE) {
      //if($this->myassertText($page,"/Where to Begin\.\.\./") !== TRUE) {
      $this->fail("FAIL! Did not find string 'This login uses HTTP', Is user logged out?\n");
      //$this->fail("FAIL! Did not find string 'Where to Begin...', Is user logged out?\n");
      $this->setUser(NULL);
      $this->setPassword(NULL);
      return(FALSE);
    }
    return(TRUE);
  }

  private function _repoDBlogin($browser = NULL) {

    if (is_null($browser))
    {
      $this->_browser();
    }

    global $URL;
    global $USER;
    global $PASSWORD;

    $page = NULL;
    $cookieValue = NULL;

    $host = getHost($URL);
    $this->assertTrue(is_object($this->mybrowser));
    $this->mybrowser->useCookies();
    $cookieValue = $this->mybrowser->getCookieValue($host, '/', 'Login');
    $this->mybrowser->setCookie('Login', $cookieValue, $host);
    $page = $this->mybrowser->get("$URL?mod=auth&nopopup=1");
    $this->assertTrue($page);

    /* Use the test configured user if none specified */
    if(!strlen($this->User)) {
      $this->setUser($USER);
    }
    // no check on the password, as it could be blank, just use it...It should
    // have been set (if there was one) in Login
    //echo "FTDB: user is:$this->User and Password:$this->Password\n";
    $this->assertTrue($this->mybrowser->setField('username', $this->User),
      "Fatal! could not set username field in login form for $this->User\n");
    $this->assertTrue($this->mybrowser->setField('password', $this->Password),
      "Fatal! could not set password field in login form for $this->User\n");
    $this->assertTrue($this->mybrowser->isSubmit('Login'));
    $page = $this->mybrowser->clickSubmit('Login');
    $this->assertTrue($page,"FATAL! did not get a valid page back from Login\n");
    //print "DB: _RDBL: After Login ****page is:$page\n";
    $page = $this->mybrowser->get("$URL?mod=Default");
    //$p = $this->__chopPage($page);
    //print "DB: _RDBL: After mod=Default ****page is:$page\n";
    $this->assertTrue($this->myassertText($page, "/User:<\/small>\s$this->User/"),
      "Did not find User:<\/small> $this->User\nThe User may not be logged in\n");
    $this->mybrowser->setCookie('Login', $cookieValue, $host);
    $page = $this->mybrowser->getContent();
    $NumMatches = preg_match('/User Logged Out/', $page, $matches);
    $this->assertFalse($NumMatches, "User Logged out!, Login Failed! %s");
    return ($cookieValue);
  } // _repoDBlogin

  public function myassertText($page, $pattern) {
    $NumMatches = preg_match($pattern, $page, $matches);
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
   * @return mixed $select either array (if first two args present) or
   * int if all three arguments present. NULL on error.
   *
   * Format of the array returned:
   *
   * Array[option text]=>[option value attribute]
   */
  public function parseSelectStmnt($page,$selectName,$optionText=NULL) {
    if(empty($page)) {
      return(NULL);
    }
    if(empty($selectName)) {
      return(NULL);
    }
    $hpage = new DOMDocument();
    @$hpage->loadHTML($page);
    /* get select and options */
    $selectList = $hpage->getElementsByTagName('select');
    $optionList = $hpage->getElementsByTagName('option');
    /*
     * gather the section names and group the options with each section
     * collect the data at the same time.  Assemble into the data structure.
     */
    for($i=0; $i < $selectList->length; $i++) {
      $ChildList = $selectList->item($i)->childNodes;
      foreach($ChildList as $child) {
        $optionValue = $child->getAttribute('value');
        $orig = $child->nodeValue;
        /*
         * need to clean up the string, to get rid of &nbsp codes, or the keys
         * will not match.
         */
        $he = htmlentities($orig);
        $htmlGone = preg_replace('/&.*?;/','',$he);
        $cleanText = trim($htmlGone);
        if(!empty($optionText)) {
          $noDotOptText = escapeDots($optionText);
          $match = preg_match("/^$noDotOptText$/", $cleanText, $matches);
          if($match) {
            /* Use the matched optionText instead of the whole string */
            //print "Adding matches[0] to select array\n";
            $Selects[$selectList->item($i)->getAttribute('name')][$matches[0]] = $optionValue;
          }
        }
        else {
          /*
           * Add the complete string contained in the <option>, any
           * html & values should have been removed.
           */
          //print "Adding cleanText to select array\n";
          $Selects[$selectList->item($i)->getAttribute('name')][$cleanText] = $optionValue;
          $foo = $selectList->item($i)->getAttribute('onload');
        }
      }
    } //for

    /*
     * if there were no selects found, then we were passed something that
     * doesn't exist.
     */
    if (empty($Selects)) {
      return(NULL);
    }
    //print "FTPSS: selects array is:\n";print_r($Selects) . "\n";
    /* Return either an int or an array */
    if (!is_null($optionText)) {
      if(array_key_exists($optionText,$Selects[$selectName])){
        return($Selects[$selectName][$optionText]);   // int
      }
      else {
        return(NULL);
      }
    }
    else {
      if(array_key_exists($selectName,$Selects)){
        return($Selects[$selectName]);            // array
      }
      else {
        return(NULL);     // didn't find any...
      }
    }
  }  // parseSelectStmnt


  /**
   * function parseFossjobs
   *
   * parse the output of fossjobs command, return an array with the information
   *
   * With no parameters parseFossnobs will return an associative array with
   * the last uploads done on each file.  The array key is the filename, and
   * upload Id is the value.  The array is reverse sorted by upload
   * (newest uploads 1st).
   *
   * With the all parameter, all of the uploads are returned in an associative
   * array.  The keys are the upload id's in assending order, the filename uploaded
   * is the value.
   *
   * @param boolean $all, indicates all uploads are wanted.
   *
   * @return associative array
   *
   */
  public function parseFossjobs($all=NULL) {
    /*
     use fossjobs to get the upload ids
     */
    $last = exec('fossjobs -u',$uploadList, $rtn);
    foreach ($uploadList as $upload) {
      if(empty($upload)) {
        continue;
      }

      list($upId, $file) = split(' ', $upload);
      if($upId == '#') {
        continue;
      }

      $uploadId = rtrim($upId, ':');
      $Uploads[$uploadId] = $file;
      /*
       gather up the last uploads done on each file (file is not unique)
       */
      $LastUploads[$file] = $uploadId;
    }
    $lastUp = &$LastUploads;
    $sorted = arsort($lastUp);
    //$sorted = arsort(&$LastUploads);
    if(!empty($all)) {
      //print "uploads is:\n";print_r($Uploads) . "\n";
      return($Uploads);               // return all uploads
    }
    else {
      //print "LastUploads is:\n";print_r($LastUploads) . "\n";
      return($LastUploads);           // default return
    }
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
   * @return NULL, or string on error
   *
   */
  public function setAgents($agents = NULL) {
    $agentList = array (
      'buckets'   => 'Check_agent_bucket',
      'copyright' => 'Check_agent_copyright',
      'mimetype' => 'Check_agent_mimetype',
      'nomos' => 'Check_agent_nomos',
      'package' => 'Check_agent_pkgagent',
    );
    /* check parameters and parse */
    if (is_null($agents)) {
      return NULL; // No agents to set
    }
    /* set them all if 'all' */
    if (0 === strcasecmp($agents, 'all')) {
      foreach ($agentList as $agent => $name) {
        if ($this->debug) {
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

    if ($numAgents = 0) {
      return NULL; // no agents to schedule
    }
    else {
      foreach ($numberList as $number) {
        switch ($number) {
          case 1 :
            $checklist[] = $agentList['buckets'];
            break;
          case 2 :
            $checklist[] = $agentList['copyright'];
            break;
          case 3 :
            $checklist[] = $agentList['mimetype'];
            break;
          case 4 :
            $checklist[] = $agentList['nomos'];
            break;
          case 5 :
            $checklist[] = $agentList['package'];
            break;
          case 6:
            $checklist[] = $agentList['specagent'];
            break;
          case 7:
            $checklist[] = $agentList['license'];
            break;
        }
      } // foreach

      if ($this->debug == 1) {
        print "the agent list is:\n";
      }

      foreach ($checklist as $agent) {
        if ($this->debug) {
          print "DEBUG: $agent\n";
        }
        $this->assertTrue($this->mybrowser->setField($agent, 1));
      }
    }
    return (NULL);
  } //setAgents

  /**
   * getSelectAttr
   *
   * get select attributes.
   *
   * @param string $page the page to parse
   * @param string $selectName the name of the select,
   *
   * @return array an array of the attributes, with the attributes as the keys.
   * NULL on errror.
   *
   */
  protected function getSelectAttr($page, $selectName){

    if(empty($page)) {
      return(NULL);
    }
    if(empty($selectName)) {
      return(NULL);
    }
    $hpage = new DOMDocument();
    @$hpage->loadHTML($page);
    /* get select */
    $selectList = $hpage->getElementsByTagName('select');
    //print "number of selects on this page:$selectList->length\n";
    /*
    * gather the section names and group the attributes with each section
    * collect the data at the same time.  Assemble into the data structure.
    */
    $select = array();
    for($i=0; $i < $selectList->length; $i++) {
      $sname = $selectList->item($i)->getAttribute('name');
      if($sname == $selectName) {
        /* get some common interesting attributes needed */
        $onload = $selectList->item($i)->getAttribute('onload');
        $onchange = $selectList->item($i)->getAttribute('onchange');
        $id = $selectList->item($i)->getAttribute('id');
        $select[$sname] = array ('onload'   => $onload,
                                 'onchange' => $onchange,
                                 'id'       => $id
        );
        break;            // all done
      }
    }
    return($select);
  }

  /**
   * setSelectAttr
   *
   * set select attributes.
   *
   * @param string $page the page to parse
   * @param string $selectName the name of the select
   * @param string $attribute the name of the attribute to change, if the attribute
   * is not already set, this method will not set it.
   * @param string $value the value for the attribute
   *
   * @return TRUE on success, NULL on error
   *
   */
  protected function setSelectAttr($page, $selectName, $attribute, $value=NULL){

    if(empty($page)) {
      return(NULL);
    }
    if(empty($selectName)) {
      return(NULL);
    }
    if(empty($attribute)) {
      return(NULL);
    }
    $hpage = new DOMDocument();
    @$hpage->loadHTML($page);
    /* get select */
    $selectList = $hpage->getElementsByTagName('select');
    /*
     * gather the section names and group the attributes with each section
     * collect the data at the same time.  Assemble into the data structure.
     */
    $select = array();
    for($i=0; $i < $selectList->length; $i++) {
      $sname = $selectList->item($i)->getAttribute('name');
      if($sname == $selectName) {
        $oldValue= $selectList->item($i)->getAttribute($attribute);
        if(!empty($value)) {
          //$node = $selectList->item($i)->set_attribute($attribute,$value);
          $node = $selectList->item($i)->setAttribute($attribute,$value);
        }
        break;      // all done
      }
    }
    $setValue= $selectList->item($i)->getAttribute($attribute);
    if($setValue != $value) {
      return(NULL);
    }
    return(TRUE);
  } // set SelectAttr

  /**
   * __chopPage
   *
   * return the last 1.5K characters of the string, useful for just looking at
   * the end of a returned page.
   *
   * @param string $page
   * @return string
   */
  private function __chopPage($page) {

    if(!strlen($page)) {
      return(FALSE);
    }
    return(substr($page,-1536));
  } // chopPage

} // fossolgyTest
