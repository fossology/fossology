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
 * Run the unit tests for the classes
 *
 *
 * @version "$Id: $"
 *
 * Created on Jun 20, 2008
 */
// Standard requires for any test.
require_once('/usr/local/simpletest/unit_tester.php');
require_once('/usr/local/simpletest/reporter.php');

class GpClassTestSuite extends TestSuite
{
  public $test_file;
  //function __construct($testfile)
  function __construct()
  {
//    $this->test_file = $testfile;
    $this->TestSuite("GpClasses - test common classes used in getting projects\n");
    $this->addTestFile('tReadInFile.php');
  }
}
?>
