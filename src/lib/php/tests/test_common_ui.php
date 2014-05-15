<?php
/*
Author: Steffen Weber
Copyright (C) 2013-2014, Siemens AG

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
 * \file test_common_ui.php
 * \brief unit tests for common-ui.php
 */

require_once('../common-ui.php');

/**
 * \class test_common_dir
 */
class test_common_ui extends PHPUnit_Framework_TestCase
{
  /* initialization */
  protected function setUp()
  {
    //print "Starting unit test for common-ui.php\n";
    print('.');
  }
  
  /**
   * \brief clean the env
   */
  protected function tearDown() {
    print "Ending unit test for common-ui.php\n";
  }
  
  /**
   * \brief test for HumanSize
   */
  function test_HumanSize()
  {
    print "test function HumanSize()\n";
    $result = HumanSize(1024*1024*1024);
    $this->assertEquals("1 GB", $result);
    $result = HumanSize(10240);
    $this->assertEquals("10 KB", $result);
    $result = HumanSize( 1024*(1024*99+511) );
    $this->assertEquals("99.5 MB", $result);
  }

}
