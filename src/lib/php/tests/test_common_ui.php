<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Steffen Weber

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file test_common_ui.php
 * \brief unit tests for common-ui.php
 */

require_once(dirname(__FILE__) . '/../common-ui.php');

/**
 * \class test_common_dir
 */
class test_common_ui extends \PHPUnit\Framework\TestCase
{
  /* initialization */
  protected function setUp() : void
  {
    //print "Starting unit test for common-ui.php\n";
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() : void
  {
  }

  /**
   * \brief test for HumanSize
   */
  function test_HumanSize()
  {
    $result = HumanSize(1024 * 1024 * 1024);
    $this->assertEquals("1 GB", $result);
    $result = HumanSize(10240);
    $this->assertEquals("10 KB", $result);
    $result = HumanSize(1024 * (1024 * 99 + 511));
    $this->assertEquals("99.5 MB", $result);
  }
}
