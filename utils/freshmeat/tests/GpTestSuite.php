<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Run the unit tests for the classes
 *
 *
 * @version "$Id: $"
 *
 * Created on Jun 20, 2008
 */
// Standard requires for any test.
if (!defined('SIMPLE_TEST'))
  define('SIMPLE_TEST', '/usr/local/simpletest/');

/* simpletest includes */
require_once SIMPLE_TEST . 'unit_tester.php';
require_once SIMPLE_TEST . 'reporter.php';
//require_once SIMPLE_TEST . 'mock_objects.php';
//require_once SIMPLE_TEST . 'web_tester.php';

class GpClassTestSuite extends TestSuite
{
  public $test_file;
  //function __construct($testfile)
  function __construct()
  {
//    $this->test_file = $testfile;
    $this->TestSuite("GpClasses - test common classes used in getting projects\n");
    //$this->addTestFile('tReadInFile.php');
    $this->addTestFile('tfmrdf.php');
  }
}
