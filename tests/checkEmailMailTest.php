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
 * Test checkMail function
 *
 *
 * @version "$Id$"
 *
 * Created on March 26, 2009
 */

require_once ('fossologyTestCase.php');
require_once ('TestEnvironment.php');

/* Globals for test use, most tests need $URL, only login needs the others */
global $URL;
global $USER;
global $PASSWORD;

class someTest extends fossologyTestCase
{
  public $mybrowser;

  function setUp() {
    global $URL;
    $this->Login();
  }

  public function testCheckCompletedJobs() {
    global $URL;

    print "starting CheckCompletedJobs\n";

    $headers = getMailSubjects();
    if(empty($headers)){
      print "No messages found\n";
      $this->pass();
      return(NULL);
    }
    //print "Got back from checkMail:\n";print_r($headers) . "\n";
    /* find any duplicates, count them */
    $pattern = 'completed with no errors';

    foreach($headers as $header) {
      /* Make sure all say completed */
      $match = preg_match("/$pattern/",$header,$matches);
      if($match == 0) {
        $failed[] = $header;
      }
    }
    if(!empty($failed)) {
      $this->fail("the following jobs did not report as completed\n");
      foreach($failed as $fail) {
        print "$fail\n";
      }
    }
  }
}
?>
