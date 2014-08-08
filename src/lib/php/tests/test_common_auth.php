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
 * \file test_common_auth.php
 * \brief unit tests for common-auth.php
 */

require_once(dirname(__FILE__) . '/../common-auth.php');

/**
 * \class test_common_auth
 */
class test_common_auth extends PHPUnit_Framework_TestCase
{
  /* initialization */
  protected function setUp()
  {
    print "Starting unit test for common-auth.php\n";
  }

  /**
   * \brief test for siteminder_check()
   */
  function test_siteminder_check()
  {
    $_SERVER['HTTP_SMUNIVERSALID'] = NULL;
    $result = siteminder_check();
    $this->assertEquals("-1", $result );
    $_SERVER['HTTP_SMUNIVERSALID'] = "Test Siteminder";
    $result = siteminder_check();
    $this->assertEquals("Test Siteminder", $result);
  }


  /**
   * \brief clean the env
   */
  protected function tearDown() {
    print "Ending unit test for common-auth.php\n";
  }
}

?>
