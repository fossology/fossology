<?php
/*
 SPDX-FileCopyrightText: © 2010 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Verify zend license was processed correctly by nomos
 *
 * @param needs to have the zend-license uploaded.
 *
 * @return pass or fail
 *
 * @version "$Id$"
 *
 * Created on Sept. 14, 2010
 */
$where = dirname(__FILE__);
if(preg_match('!/home/jenkins.*?tests.*!', $where, $matches))
{
  //echo "running from jenkins....fossology/tests\n";
  require_once ('fossologyTestCase.php');
  require_once('testClasses/parseMiniMenu.php');
  require_once('testClasses/parseBrowseMenu.php');
  require_once('commonTestFuncs.php');

}
else
{
  //echo "using requires for running outside of jenkins\n";
  require_once ('../fossologyTestCase.php');
  require_once('../testClasses/parseMiniMenu.php');
  require_once('../testClasses/parseBrowseMenu.php');
  require_once('../commonTestFuncs.php');

}

global $URL;

class zendTest extends fossologyTestCase
{
  public $mybrowser;          // must have
  public $someOtherVariable;
  protected $host;

  /*
   * Every Test needs to login so we use the setUp method for that.
   * setUp is called before any other method by default.
   *
   */
  function setUp()
  {
    global $URL;
    $this->Login();
    $this->host = getHost($URL);
  }

  function testZendLic()
  {
    global $URL;

    print "starting testZendLic\n";

    $name = 'zend-license';
    $page = $this->mybrowser->clickLink('Browse');
    $this->assertTrue($this->myassertText($page, "/>View</"),
       "ckzend FAILED! >View< not found\n");
    $this->assertTrue($this->myassertText($page, "/>Info</"),
       "ckzend FAILED! >Info< not found\n");
    $this->assertTrue($this->myassertText($page, "/>Download</"),
       "ckzend FAILED! >Download< not found\n");
    $page = $this->mybrowser->clickLink('Testing');
    $this->assertTrue($this->myassertText($page, '/Testing/'),
     "ckzend FAILED! Could not find Testing folder\n");
    $this->assertTrue($this->myassertText($page, "/$name/"),
       "ckzend FAILED! did not find $name\n");

    /* Select archive */
    //print "CKZDB: page before call parseBMenu:\n$page\n";

    $browse = new parseBrowseMenu($page, 'browsetbl', 1);
    $browse->parseBrowseMenuFiles();
    // get the View link for zend-license
    $viewLink = $browse->browseList[$name]['View'];
    $page = $this->mybrowser->get($viewLink);
    $mini = new parseMiniMenu($page);
    $miniMenu = $mini->parseMiniMenu();
    $url = makeUrl($this->host, $miniMenu['License Browser']);
    if($url === NULL) { $this->fail("ckzend Failed, host/url is not set"); }

    $this->assertTrue($this->myassertText($page, '/View File/'),
          "ckzend FAILED! View File Title not found\n");
    $page = $this->mybrowser->get($url);
    // Check License
    // Get the displayed result
    $matched = preg_match("/<hr>\nThe(.*?)<div class='text'>--/", $page, $matches);
    //print "DBCKZ: we found:\n";print_r($matches) . "\n";
    $foundRaw = $matches[1];
    $stripped = strip_tags($foundRaw);
    $found = escapeDots($stripped);

    $stringToMatch = 'Nomos license detector found: Zend_v2\.0';
    $this->assertTrue($found,"/$stringToMatch/",
          "ckzend FAILED! Nomos license string does not match\n" .
		      "Expected: $stringToMatch\n" .
		      "     Got: $found\n");
    $this->assertTrue($this->myassertText($page, '/View License/'),
          "ckzend FAILED! View License Title not found\n");
    // Check One-shot Analysis
    $urlOneShot = makeUrl($this->host, $miniMenu['One-Shot License']);
    if($urlOneShot === NULL) { $this->fail("ckzend Failed, cannot make One-Shot url"); }
    $page = $this->mybrowser->get($urlOneShot);
    $this->assertTrue($this->myassertText($page, '/One-Shot License Analysis/'),
          "ckzend FAILED! One-Shot License Analysis Title not found\n");
    //$osLicText = '<strong>Zend_v2\.0';
    //$this->assertTrue($this->myassertText($page, "/$osLicText/"),
    //"ckzend FAILED! the text:\n$osLicText\n was not found\n");
  }
}
