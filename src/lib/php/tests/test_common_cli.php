<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file test_common_cli.php
 * \brief unit tests for common-cli.php
 */

require_once(dirname(__FILE__) . '/../common-cli.php');

/**
 * \class test_common_cli
 */
class test_common_cli extends \PHPUnit\Framework\TestCase
{
  /**
   * \brief initialization
   */
  protected function setUp() : void
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
  protected function tearDown() : void
  {
    if (file_exists("./cli.log")) {
      unlink("./cli.log");
    }
  }
}
