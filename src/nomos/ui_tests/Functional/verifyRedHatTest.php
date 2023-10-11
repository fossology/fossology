<?php
/*
 SPDX-FileCopyrightText: © 2010 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Verify/Establish baseline of licenses found by nomos
 *
 * @param needs to have the RedHat.tar uploaded.
 *
 * @return pass or fail
 *
 * @version "$Id$"
 *
 * Created on Oct. 6, 2010
 */
$where = dirname(__FILE__);
if(preg_match('!/home/jenkins.*?tests.*!', $where, $matches))
{
  //echo "running from jenkins....fossology/tests\n";
  require_once ('fossologyTestCase.php');
  //require_once ('../TestEnvironment.php');
  require_once('testClasses/parseMiniMenu.php');
  require_once('testClasses/dom-parseLicenseTable.php');
  require_once('testClasses/parseFolderPath.php');
  require_once('commonTestFuncs.php');
}
else
{
  //echo "using requires for running outside of jenkins\n";
  require_once ('../fossologyTestCase.php');
  //require_once ('../TestEnvironment.php');
  require_once('../testClasses/parseMiniMenu.php');
  require_once('../testClasses/dom-parseLicenseTable.php');
  require_once('../testClasses/parseFolderPath.php');
  require_once('../commonTestFuncs.php');
}

/* Globals for test use, most tests need $URL, only login needs the others */
global $URL;

class rhelTest extends fossologyTestCase
{
  public $mybrowser;          // must have
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

  function testRHEL()
  {
    global $URL;

    $licBaseLine = array(
    'No_license_found' => 6878,
    'Apache_v2.0' => 858,
    'ATT' => 812,
    'GPL_v2+' => 289,
    'CMU' => 247,
    'FSF' => 176,
    'BSD-style' => 154,
    'LGPL' => 131,
    'GPL_v3+' => 76,
    'Apache-possibility' => 62,
    'See-doc(OTHER)' => 28,
    'Debian-SPI-style' => 34,
    'GNU-Manpages' => 34,
    'LGPL_v2.1+' => 29,
    'MPL_v1.1' => 29,
    'Trademark-ref' => 25,
    'IETF' => 28,
    'UnclassifiedLicense' => 28,
    'GPL-exception' => 24,
    'BSD' => 20,
    'Apache' => 17,
    'GPL' => 73,
    'GPL_v2' => 13,
    'GPL_v2.1+' => 1,
    'Indemnity' => 2,
    'GPL-possibility' => 1,
    'Public-domain' => 20,
    'Non-commercial!' => 6,
    'RSA-Security' => 7,
    'ATT-possibility' => 6,
    'LGPL_v2+' => 6,
    'OSL_v1.0' => 6,
    'CPL_v1.0' => 5,
    'GFDL_v1.1+' => 5,
    'GPL_v3' => 5,
    'Intel' => 5,
    'LGPL_v3+' => 4,
    'Public-domain-ref' => 4,
    'Perl-possibility' => 4,
    'APSL_v1.1' => 3,
    'IOS' => 3,
    'MIT-style' => 3,
    'NOT-public-domain' => 10,
    'Apache_v1.1' => 3,
    'CMU-possibility' => 2,
    'GFDL_v1.1' => 3,
    'GFDL_v1.2+' => 3,
    'MIT' => 2,
    'MPL' => 2,
    'Open-Publication_v1.0' => 2,
    'Zope-PL_v2.0' => 2,
    'AGFA(RESTRICTED)' => 1,
    'APSL' => 1,
    'APSL_v1.2' => 1,
    'ATT-Source_v1.2d' => 1,
    'BSD-possibility' => 1,
    'CCA' => 1,
    'Dyade' => 1,
    'HP-possibility' => 1,
    'ISC' => 1,
     'GPL-Bison-exception' => 1,
    'LGPL-possibility' => 1,
    'LGPL_v2' => 1,
    'MacroMedia-RPSL' => 1,
    'Microsoft-possibility' => 1,
    'NPL_v1.1' => 1,
    'RedHat-EULA' => 1,
    'RedHat(Non-commercial)' => 1,
    'Same-license-as' => 1,
    'See-file(COPYING)' => 1,
    'Sun' => 1,
    'Sun-BCLA' => 1,
    'Sun-possibility' => 1,
    'Sun(RESTRICTED)' => 1,
    'TeX-exception' => 1,
    'U-Wash(Free-Fork)' => 1,
    'X11' => 1,
    'zlib/libpng' => 1,
    );

    $licenseSummary = array(
      'Unique licenses'        => 71,
      'Licenses found'         => 3328,
      'Files with no licenses' => 6878,
      'Files'                  => 12595
    );


    print "starting testRHEL\n";

    $name = 'RedHat.tar.gz';
    $safeName = escapeDots($name);
    //print "safeName is:$safeName\n";
    $page = $this->mybrowser->clickLink('Browse');
    $page = $this->mybrowser->clickLink('Testing');
    $this->assertTrue($this->myassertText($page, "/$safeName/"),
       "verifyRedHat FAILED! did not find $safeName\n");

    /* Select archive */
    //print "CKZDB: page before call parseBMenu:\n$page\n";

    $page = $this->mybrowser->clickLink($name);
    $this->assertTrue($this->myassertText($page, "/1 item/"),
       "verifyRedHat FAILED! 1 item not found\n");
    $page = $this->mybrowser->clickLink('RedHat.tar');
    //print "page after clicklink RedHat.tar:\n$page\n";
    $page = $this->mybrowser->clickLink('RedHat/');
    //print "page after clicklink RedHat:\n$page\n";
    $this->assertTrue($this->myassertText($page, "/65 items/"),
       "verifyRedHat FAILED! '65 items' not found\n");
    $mini = new parseMiniMenu($page);
    $miniMenu = $mini->parseMiniMenu();
    //print "miniMenu is:\n";print_r($miniMenu) . "\n";
    $url = makeUrl($this->host, $miniMenu['License Browser']);
    if($url === NULL) { $this->fail("verifyRedHat Failed, host/url is not set"); }

    $page = $this->mybrowser->get($url);
    //print "page after get of $url is:\n$page\n";
    $this->assertTrue($this->myassertText($page, '/License Browser/'),
          "verifyRedHat FAILED! License Browser Title not found\n");

    // check that license summarys are correct
    $licSummary = new domParseLicenseTbl($page, 'licsummary', 0);
    $licSummary->parseLicenseTbl();

    print "verifying summaries....\n";
    foreach ($licSummary->hList as $summary)
    {
      //print "summary is:\n";print_r($summary) . "\n";
      $key = $summary['textOrLink'];
      //print "SUM: key is:$key\n";
      $this->assertEqual($licenseSummary[$key], $summary['count'],
      "verifyRedHat FAILED! $key does not equal $licenseSummary[$key],
      Expected: {$licenseSummary[$key]},
           Got: $summary[count]\n");
      //print "summary is:\n";print_r($summary) . "\n";
    }

    // get the license names and 'Show' links
    $licHistogram = new domParseLicenseTbl($page, 'lichistogram',1);
    $licHistogram->parseLicenseTbl();

    if($licHistogram->noRows === TRUE)
    {
      $this->fail("FATAL! no table rows to process, there should be many for"
      . " this test, Stopping the test");
      return;
    }

    // verify every row against the standard by comparing the counts.
    /*
    * @todo check the show links, but to do that, need to gather another
    * standard array to match agains?  or just use the count from the
    * baseline?
    */

    //print "table is:\n";print_r($licHistogram->hList) . "\n";
    foreach($licHistogram->hList as $licFound)
    {
      $key = $licFound['textOrLink'];
      //print "HDB: key is:$key\n";
      //print "licFound is:\n";print_r($licFound) . "\n";
      if(array_key_exists($key,$licBaseLine))
      {
        //print "licFound[textOrLink] is:{$licFound['textOrLink']}\n";
        $this->assertEqual($licBaseLine[$key], $licFound['count'],
          "verifyRedHat FAILED! the baseline count {$licBaseLine[$key]} does" .
          " not equal {$licFound['count']} for license $key,\n" .
          "Expected: {$licBaseLine[$key]},\n" .
          "     Got: {$licFound['count']}\n");
      }
      else
      {
        $this->fail("verifyRedHat FAILED! A License was found that is " .
         "not in the standard:\n$key\n");
      }
    }
  }
}
