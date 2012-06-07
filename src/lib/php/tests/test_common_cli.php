<?php
/*
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
 * \file test_common_cli.php
 * \brief unit tests for common-cli.php
 */

require_once('../common-cli.php');

/**
 * \class test_common_cli
 */
class test_common_cli extends PHPUnit_Framework_TestCase
{
  /**
   * \brief initialization
   */
  protected function setUp() 
  {
  }

  /**
   * \brief test for cli_logger 
   */
  function testcli_logger()
  {
    print "Start unit test for common-cli.php\n";
    print "test function cli_logger()\n";
    $data = "test for cli log";
    cli_logger("./cli.log", $data, "w");
    $file_contents = file_get_contents("./cli.log");
    $this->assertEquals("$data\n", $file_contents);
    cli_logger("./cli.log", $data, "a");
    $file_contents = file_get_contents("./cli.log");
    $this->assertEquals("$data\n$data\n", $file_contents);
    cli_logger("./cli.log", $data, "w");
    $file_contents = file_get_contents("./cli.log");
    $this->assertEquals("$data\n", $file_contents);
    print "unit test for common-cli.php end\n";
  }
  
  /**
   * \brief clean the env
   */
  protected function tearDown() {
    if (file_exists("./cli.log"))
    {
      unlink("./cli.log");
    }
  }
}

?>
