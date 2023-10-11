<?php
/*
 SPDX-FileCopyrightText: © 2010 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Verify special fossology test archive loaded correctly
 *
 * @version "$Id: verifyFossDirsOnly.php 2019 2009-04-25 03:05:10Z rrando $"
 *
 * Created on Aug 25, 2008
 */

require_once('../../../tests/fossologyTestCase.php');
require_once('../../../tests/commonTestFuncs.php');
require_once('../../../tests/TestEnvironment.php');
require_once('../../../tests/testClasses/parseBrowseMenu.php');
require_once('../../../tests/testClasses/parseMiniMenu.php');
require_once('../../../tests/testClasses/parseFolderPath.php');
require_once('../../../tests/testClasses/parseLicenseTbl.php');
require_once('../../../tests/testClasses/parseLicenseTblDirs.php');

global $URL;

/**
 * This test verifies that the archive fossDirsOnly is processed
 * correctly. The archive contains only empty directories.
 */
class verifyDirsOnly extends fossologyTestCase
{
  public $mybrowser;
  public $host;

  function setUp()
  {
    /*
     * This test requires that the fossology test archive has been
     * loaded under the name fossDirsOnly.tar.bz2
     */
    global $URL;
    global $name;
    global $safeName;

    print "starting verifyFossDirsOnly-SetUp\n";
    $name = 'fossDirsOnly.tar.bz2';
    $safeName = escapeDots($name);
    $this->host = getHost($URL);
    $this->Login();

    /* check for existense of archive */
    $page = $this->mybrowser->get($URL);
    $page = $this->mybrowser->clickLink('Browse');
    $this->assertTrue($this->myassertText($page, '/Browse/'),
         "verifyDirsOnly FAILED! Could not find Browse menu\n");
    $result = $this->myassertText($page, "/$safeName/");
    if(!($result)) {
      $this->fail("Failure, cannot find archive $name, Stopping test\n");
      exit(1);
    }
  }

  function testVerifyFossology()
  {
    global $URL;
    global $name;
    global $safeName;

    print "starting verifyFossDirsOnly test\n";
    $page = $this->mybrowser->clickLink('Browse');
    $this->assertTrue($this->myassertText($page, '/Browse/'),
             "verifyFossDirsOnly FAILED! Could not find Browse menu\n");
    //print "************ Page after upload link *************\n$page\n";
    $this->assertTrue($this->myassertText($page, "/$safeName/"),
       "verifyFossDirsOnly FAILED! did not find $name\n");
    $this->assertTrue($this->myassertText($page, "/>View</"),
       "verifyFossDirsOnly FAILED! >View< not found\n");
    $this->assertTrue($this->myassertText($page, "/>Meta</"),
       "verifyFossDirsOnly FAILED! >Meta< not found\n");
    $this->assertTrue($this->myassertText($page, "/>Download</"),
       "verifyFossDirsOnly FAILED! >Download< not found\n");

    /* Select archive */
    $page = $this->mybrowser->clickLink($name);
    //print "************ Page after select foss archive *************\n$page\n";
    $this->assertTrue($this->myassertText($page, "/empty\//"),
      "verifyFossDirsOnly FAILED! 'empty/' not found\n");
    $this->assertTrue($this->myassertText($page, "/1 item/"),
      "verifyFossDirsOnly FAILED! '1 item' not found\n");

    /* Select empty link */
    $page = $this->mybrowser->clickLink('empty/');

    /* need to check that there are 9 items */
    $this->assertTrue($this->myassertText($page, "/9 items/"),
      "verifyFossDirsOnly FAILED! '9 items' not found\n");
    $this->assertTrue($this->myassertText($page, "/agents/"),
    "verifyFossDirsOnly FAILED! did not find 'agents' directory\n");
    $this->assertTrue($this->myassertText($page, "/scheduler/"),
      "verifyFossDirsOnly FAILED! did not find scheduler directory\n");

    /* Select the License link to View License Historgram */
    $browse = new parseBrowseMenu($page);
    $mini = new parseMiniMenu($page);
    $miniMenu = $mini->parseMiniMenu();
    $url = makeUrl($this->host, $miniMenu['License']);
    if($url === NULL) { $this->fail("verifyFossDirsOnly Failed, host is not set"); }

    $page = $this->mybrowser->get($url);
    //print "page after get of $url is:\n$page\n";
    $this->assertTrue($this->myassertText($page, '/License Browser/'),
          "verifyFossDirsOnly FAILED! License Browser Title not found\n");
    $this->assertTrue($this->myassertText($page, '/Total licenses: 0/'),
        "verifyFossDirsOnly FAILED! Total Licenses does not equal 0\n");

    $dList = new parseLicenseTblDirs($page);
    $dirList = $dList->parseLicenseTblDirs();
    //print "dirList is:\n"; print_r($dirList) . "\n";
    /*
     * the directiory agents has 13 subdirectories all other directories
     * are empty. we are going to loop through them, but for now just
     * test a few of them out....
     */
    $url = makeUrl($this->host, $dirList['scheduler/']);
    $page = $this->mybrowser->get($url);
    //print "page after scheduler is:\n$page\n";
    $fList = new parseFolderPath($page, $url);
    $dirCnt = $fList->countFiles();
    // should only get one folder path)
    $this->assertEqual((int)$dirCnt, 1,
    "verifyFossDirsOnly FAILED! did not get 1 folder path back, got $dirCnt instead\n");
    // every entry but the last must have a non-null value (we assume parse
    // routine worked)
    $fpaths = $fList->parseFolderPath();
    $this->assertTrue($this->check4Links($fpaths),
      "verifyFossDirsOnly FAILED! something wrong with folder path\n" .
      "See this url:\n$url\n");
  }
  /**
   * check4Links
   *
   * Check to see that the folder path array passed in is constructed
   * properly.  It must consist of links till the leaf node, which
   * should not have a link.
   *
   * @param array $folderPath
   *
   * @return boolean
   *
   */
   function check4Links($folderPath)
   {
    $flistSize = count($folderPath[0]);
    foreach($folderPath as $flist)
    {
      $i = 0;
      foreach($flist as $folder => $link)
      {
        $i++;
        // is it the last entry?
        if ($i == $flistSize)
        {
          $this->assertTrue(is_null($link),
          "verifyFossDirsOnly FAILED! Last entry is not null\n$folder $link\n");
          continue;
        }
        $this->assertFalse(is_null($link),
        "verifyFossDirsOnly FAILED! Found a folder with no link\n$folder $link\n");
      }
    }
    return(TRUE);
   }
}
