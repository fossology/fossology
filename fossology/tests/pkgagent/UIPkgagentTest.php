<?php
/*
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
 */

/**
 * UIPkgagentTest.php
 * \brief Upload/Queue Job/Verfity Test of pkgagent
 *
 * @version "$Id: $"
 *
 * Created on March 30, 2010
 */

/* every test must use these includes, adjust the paths based on where the
 * tests are in the source tree.
 */
require_once ('../fossologyTestCase.php');
require_once ('../TestEnvironment.php');
require_once('../testClasses/parseBrowseMenu.php');

global $URL;

/* The class name should end in Test */

class UIPkgagentTest extends fossologyTestCase
{
  public $mybrowser;          // must have
  public $host;

  function setUp()
  {
    global $URL;
    global $name;
    global $safeName;

    $name = 'fossRpmsDebs.tar.bz2';
    $safeName = escapeDots($name);
    $this->host = getHost($URL);
    $this->Login();
  }
  /**
   * create pkgagent folder test
   */
/*  function testCreatePkgFolder()
  {
    global $URL;
    print "Creating Pkgagent folder\n";
    $this->createFolder(null, 'Pkgagent', null);
  }

  function testUploadPkgagentTestData()
  {
    global $URL;
    print "Starting testUploadPkgagentTestData\n";
    $rootFolder = 1;
    $upload = NULL;
    $pkgagentList = array ('TestData/fossRpmsDebs.tar.bz2');

    print "Starting pkgagent upload\n";
    foreach($pkgagentList as $upload) {
      $description = "File $upload uploaded by Upload Pkgagent TestData";
      $this->uploadFile('Pkgagent', $upload, $description, null, '5');
    }
  }
*/
  function testVerifyPkgagentTestData()
  {
    global $URL;
    global $name;
    global $safeName;

    print "Waiting for jobs to finish...\n";
    $last = exec('../wait4jobs.php', $tossme, $jobsDone);
    /*
    foreach($tossme as $line){
      print "$line\n";
    }
    print "testVerifyPkgagentTestData; jobsDone is:$jobsDone\n";
    */
    if ($jobsDone != 0) {
      print "ERROR! jobs are not finished after two hours, not running" .
      "verify tests, please investigate and run verify tests by hand\n";
      exit(1);
    }
    if ($jobsDone == 0) {
      print "Starting Verify Pkgagent Test\n";
      $page = $this->mybrowser->clickLink('Browse');
      $this->assertTrue($this->myassertText($page, '/Browse/'),
             "verifyPkgagent FAILED! Could not find Browse menu\n");

      $page = $this->mybrowser->clickLink('Pkgagent');
      $this->assertTrue($this->myassertText($page, "/Browse/"),
        "verifyPkgagent FAILED! Browse Title not found\n");
      $this->assertTrue($this->myassertText($page, "/$safeName/"),
        "verifyPkgagent FAILED! did not find $name\n");
      $this->assertTrue($this->myassertText($page, "/>View</"),
        "verifyPkgagent FAILED! >View< not found\n");

      /* Select archive */
      $page = $this->mybrowser->clickLink($name);
      $this->assertTrue($this->myassertText($page, "/fossRpmsDebs\//"),
        "verifyPkgagent FAILED! 'fossRpmsDebs/' not found\n");
      $this->assertTrue($this->myassertText($page, "/1 item/"),
        "verifyPkgagent FAILED! '1 item' not found\n");

      /* Select fossRpmsDebs/ link */
      $page = $this->mybrowser->clickLink('fossRpmsDebs/');

      /* need to check that there are 4 items */
      $this->assertTrue($this->myassertText($page, "/4 items/"),
        "verifyPkgagent FAILED! '4 items' not found\n");
      $this->assertTrue($this->myassertText($page, "/fossology-1.1.0-1.el4.i386.rpm/"),
        "verifyPkgagent FAILED! did not find 'fossology-1.1.0-1.el4.i386.rpm' \n");
      $this->assertTrue($this->myassertText($page, "/fossology-1.1.0-1.el4.src.rpm/"),
        "verifyPkgagent FAILED! did not find 'fossology-1.1.0-1.el4.src.rpm' \n");
      $this->assertTrue($this->myassertText($page, "/fossology_1.1.1~20100119_all.deb/"),
        "verifyPkgagent FAILED! did not find 'fossology_1.1.1~20100119_all.deb' \n");
      $this->assertTrue($this->myassertText($page, "/fossology-debsrc/"),
        "verifyPkgagent FAILED! did not find 'fossology-debsrc' directory \n");

      /* Select fossology-debsrc/ link to check debian source pacakge */
      $pagedebsrc = $this->mybrowser->clickLink('fossology-debsrc/');

      $this->assertTrue($this->myassertText($pagedebsrc, "/3 items/"),
        "verifyPkgagent FAILED! '3 items' not found\n");
      $this->assertTrue($this->myassertText($pagedebsrc, "/fossology_1.1.1~20100119.diff.gz/"),
        "verifyPkgagent FAILED! did not find 'fossology_1.1.1~20100119.diff.gz' \n");
      $this->assertTrue($this->myassertText($pagedebsrc, "/fossology_1.1.1~20100119.dsc/"),
        "verifyPkgagent FAILED! did not find 'fossology_1.1.1~20100119.dsc' \n");
      $this->assertTrue($this->myassertText($pagedebsrc, "/fossology_1.1.1~20100119.orig.tar.gz/"),
        "verifyPkgagent FAILED! did not find 'fossology_1.1.1~20100119.orig.tar.gz' \n");

      preg_match_all("/.*?\[<a href='(.*?)'.*?>(Info.*?)</", $pagedebsrc, $match, PREG_OFFSET_CAPTURE);
      //print_r($match);
      $url = makeUrl($this->host, $match[1][1][0]);
      if($url === NULL) { $this->fail("verifyPkgagent Failed, host is not set"); }
      $pagedebsrc = $this->mybrowser->get($url);
      //print "$pagedebsrc\n";
	  $this->assertTrue($this->myassertText($pagedebsrc, '/records: 15/'),
        "verifyPkgagent FAILED! Package info records: 15 not found\n");
      $this->assertTrue($this->myassertText($pagedebsrc, '/Debian Source Package/'),
        "verifyPkgagent FAILED! Debian Source Package not found\n");
      $this->assertTrue($this->myassertText($pagedebsrc, '/Matt Taggart &lt;taggart@debian.org&gt;/'),
        "verifyPkgagent FAILED! Matt Taggart <taggart@debian.org>  not found\n");
	  $this->assertTrue($this->myassertText($pagedebsrc, '/debhelper \(&gt;\= 5\)/'),
        "verifyPkgagent FAILED! debhelper (>= 5) not found\n");

      /* Select each package's [Info] link to View package info*/
      $menu = new parseBrowseMenu($page, 'pkginfo', 1);
      $list = $menu->parseBrowseFileMinis();
      //print_r($list);
      $url = makeUrl($this->host, $list[0]['Info']);
      if($url === NULL) { $this->fail("verifyPkgagent Failed, host is not set"); }
      //print "$url\n";
      $page = $this->mybrowser->get($url);
      $this->assertTrue($this->myassertText($page, '/records: 51/'),
        "verifyPkgagent FAILED! Package Info records: 51 not found\n");
      $this->assertTrue($this->myassertText($page, '/RPM Binary Package/'),
        "verifyPkgagent FAILED! RPM Binary Package not found\n");
      $this->assertTrue($this->myassertText($page, '/Sun Jul 19 10:54:54 2009/'),
        "verifyPkgagent FAILED! Sun Jul 19 10:54:54 2009 not found\n");
      $this->assertTrue($this->myassertText($page, '/fossology-1.1.0-1.el4.src.rpm/'),
        "verifyPkgagent FAILED! fossology-1.1.0-1.el4.src.rpm not found\n");

      $url = makeUrl($this->host, $list[1]['Info']);
      if($url === NULL) { $this->fail("verifyPkgagent Failed, host is not set"); }
      //print "$url\n";
      $page = $this->mybrowser->get($url);
      $this->assertTrue($this->myassertText($page, '/records: 24/'),
        "verifyPkgagent FAILED! Package Info records: 24 not found\n");
      $this->assertTrue($this->myassertText($page, '/RPM Source Package/'),
        "verifyPkgagent FAILED! RPM Source Package not found\n");
      $this->assertTrue($this->myassertText($page, '/x86_64/'),
        "verifyPkgagent FAILED! x86_64 not found\n");
      $this->assertTrue($this->myassertText($page, '/perl-Text-Template/'),
        "verifyPkgagent FAILED! perl-Text-Template not found\n");

      $url = makeUrl($this->host, $list[2]['Info']);
      if($url === NULL) { $this->fail("verifyPkgagent Failed, host is not set"); }
      //print "$url\n";
      $page = $this->mybrowser->get($url);
      $this->assertTrue($this->myassertText($page, '/records: 12/'),
        "verifyPkgagent FAILED! Package Info records: 12 not found\n");
      $this->assertTrue($this->myassertText($page, '/Debian Binary Package/'),
        "verifyPkgagent FAILED! Debian Binary Package not found\n");
      $this->assertTrue($this->myassertText($page, '/utils/'),
        "verifyPkgagent FAILED! utils not found\n");
      $this->assertTrue($this->myassertText($page, '/36/'),
        "verifyPkgagent FAILED! 36 not found\n");
    }
  }
}
?>
