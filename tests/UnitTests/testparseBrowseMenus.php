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
 * use/test the parseminimenu class
 *
 * @param
 *
 * @return
 *
 * @version "$Id$"
 *
 * Created on Aug 20, 2008
 */

//require_once ('../testClasses/parseBrowseMenu.php');
require_once ('testClasses/parseBrowseMenu.php');
require_once ('fossologyWebTestCase.php');
require_once ('TestEnvironment.php');

global $URL;

class testParseBrowseMenu extends fossologyWebTestCase
{
  public $mybrowser;

  function setUp()
  {
    global $URL;
    print "TestparseBrowseMini is running\n";
    $browser = & new SimpleBrowser();
    $page = $browser->get($URL);
    $this->assertTrue($page);
    $this->assertTrue(is_object($browser));
    $this->mybrowser = $browser;
    $cookie = $this->repoLogin($this->mybrowser);
    $this->host = $this->getHost($URL);
    $this->mybrowser->setCookie('Login', $cookie, $host);
  }
  function testBrowseMenu()
  {
    /*
     * The test data depends on having the special fossology test
     * archive uploaded.
     * First test is a mix of files and directories
     * Second test is only files
     * Third test is only a directory
     */
    $testPages = array (
     'http://snape.west/repo/?mod=browse&folder=1&show=detail&upload=155&item=49948',
     'http://snape.west/repo/?mod=browse&folder=1&show=detail&upload=155&item=50013',
     'http://snape.west/repo/?mod=browse&upload=155&item=49947&folder=1&show=detail'
                        );
    /*
     * testCounts is the number to test against. Each row below
     * represents the counts for files, fileminis and dirs.  The count
     * for file and fileminis should always be equal.
     *
     * This will make the this test a bit brittle, but needed to ensure
     * proper operation. Think about using mocks in the future.
		 */
    $testCounts = array(7, 7,  8,
                        9, 9,  0,
                        0, 0,  1);

    /* navigate to a page to test*/
    $index = 0;
    foreach ($testPages as $link)
    {
      //print "navigating to:\n";
      //print "$link\n";
      $page = $this->mybrowser->get($link);
      $bmenu = new parseBrowseMenu($page);
      $parsed = $bmenu->parseBrowseMenuFiles();
      $fileCount = count($parsed);
      $this->assertEqual($fileCount,$testCounts[$index],
             "FAIL! File counts did not match\n");
      $index++;

      $parsed = $bmenu->parseBrowseFileMinis();
      $this->assertEqual(count($parsed),$testCounts[$index],
             "FAIL! FileMini counts did not match\n");
      $index++;
      /* Make sure files = fileminis */
      $this->assertEqual(count($parsed),$fileCount,
             "FAIL! File counts does not equal Fileminis\n");

      $parsed = $bmenu->parseBrowseMenuDirs();
      $this->assertEqual(count($parsed),$testCounts[$index],
             "FAIL! Directory counts are not equal\n");
      $index++;
    }
    /*
     * if (!empty ($parsed))
      {
        print "parsed files are:\n";
        print_r($parsed) . "\n";
      }

     */
    //print "DB: parsed is:\n";
    //print_r($parsed) . "\n";
  }
}
?>
