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
  /* initialization */
  protected function setUp() 
  {
    /** require PHPUnit/Framework.php */
    print "Start unit test for common-cli.php\n";
    $php_lib1 = "/usr/share/php/PHPUnit/Framework.php";
    $php_lib2 = "/usr/share/pear/PHPUnit/Framework.php";
    if(file_exists($php_lib1))
    {
      require_once($php_lib1);
    }
    else if(file_exists($php_lib2)) 
    {
      require_once($php_lib2);
    }
    else
    {
      die("Could not find PHPUnit/Framework.php\n");
    }
  }

  /**
   * \brief test for cli_logger 
   */
  function testcli_logger()
  {
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
  }
  
  /**
   * \brief clean the env
   */
  protected function tearDown() {
    if (file_exists("./cli.log"))
    {
      unlink("./cli.log");
    }
    print "unit test for common-cli.php end\n";
  }
}

?>
