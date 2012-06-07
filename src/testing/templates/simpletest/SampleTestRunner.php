<?php
/***********************************************************
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
 ***********************************************************/

/**
 * Template to use for running a test and reporting pass/fail
 *
 * @version "$Id: $"
 *
 * Created on March 10, 2010
 */

/* every test must use these includes, adjust the paths based on where the
 * tests are in the source tree.
 */
require_once ('TestEnvironment.php');
require_once ('fossologyTestCase.php');

global $URL;

class runMyTest extends fossologyTestCase
{
  public $mybrowser;
  public $testFolder;

  /*
   * setUp is called before any other method by default.
   *
   * If other actions like creating a folder or something are needed,
   * put them in the setUp method after login.
   *
   */
  function setUp()
  {
    return(TRUE);
  }
/* all runnable test names (methods/functions) must start with 'test' */
  function testmytest()
  {
  	// exec your test.  The test should return 0 for pass 1 for fail
  	// the test can pass back more, but it must indicate pass and fail
  	$last = exec("pathToTest", $output, $rtn);
  	if ($rtn == 0) {
  		$this->pass();
  	}
  	else {
  		$this->fail();
  	}
  }
  /* use the tearDown method to clean up after a test.  This method like
   * setUp will run after every test.
   */

   function tearDown()
   {
   		return(TRUE);
   }
}
?>
