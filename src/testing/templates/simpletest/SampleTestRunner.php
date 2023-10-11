<?php
/*
 SPDX-FileCopyrightText: © 2010 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

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
