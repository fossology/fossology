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
 * @backupGlobals disabled
 * \class test_common_auth
 */
class test_common_auth extends \PHPUnit\Framework\TestCase
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
    $_SERVER['HTTP_SMUNIVERSALID'] = null;
    $result = siteminder_check();
    $this->assertEquals("-1", $result );
    $_SERVER['HTTP_SMUNIVERSALID'] = "Test Siteminder";
    $result = siteminder_check();
    $this->assertEquals("Test Siteminder", $result);
  }

  /**
   * \brief clean the env
   */
  protected function tearDown()
  {
    print "Ending unit test for common-auth.php\n";
  }

  /**
   * @brief Test for generate_password_policy()
   * @test
   * -# Setup SYSCONFIG
   * -# Enable all policy settings
   * -# Call generate_password_policy() and match expected regex
   */
  function test_generate_password_policy_all()
  {
    global $SysConf;
    $SysConf = [];
    $SysConf['SYSCONFIG'] = [];
    $SysConf['SYSCONFIG']['PasswdPolicy'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicyMinChar'] = 8;
    $SysConf['SYSCONFIG']['PasswdPolicyMaxChar'] = 16;
    $SysConf['SYSCONFIG']['PasswdPolicyLower'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicyUpper'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicyDigit'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicySpecial'] = '#%@^!*()';
    $expected = '(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[#%@^!*()])[a-zA-Z\\d#%@^!*()]{8,16}';
    $actual = generate_password_policy();
    $this->assertEquals($expected, $actual);
  }

  /**
   * @brief Test for generate_password_policy() when policy is disabled
   * @test
   * -# Setup SYSCONFIG
   * -# Enable all policy settings
   * -# Disable password policy
   * -# generate_password_policy() should return regex to match all
   */
  function test_generate_password_policy_disable()
  {
    global $SysConf;
    $SysConf = [];
    $SysConf['SYSCONFIG'] = [];
    $SysConf['SYSCONFIG']['PasswdPolicy'] = 'false';
    $SysConf['SYSCONFIG']['PasswdPolicyMinChar'] = 8;
    $SysConf['SYSCONFIG']['PasswdPolicyMaxChar'] = 16;
    $SysConf['SYSCONFIG']['PasswdPolicyLower'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicyUpper'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicyDigit'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicySpecial'] = '#%@^!*()';
    $expected = '.*';
    $actual = generate_password_policy();
    $this->assertEquals($expected, $actual);
  }

  /**
   * @brief Test for generate_password_policy() when no minimum limit is set
   * @test
   * -# Setup SYSCONFIG
   * -# Enable all policy settings
   * -# Set min limit to empty
   * -# Call generate_password_policy() and match expected regex with min limit
   *    as 0
   */
  function test_generate_password_policy_no_min()
  {
    global $SysConf;
    $SysConf = [];
    $SysConf['SYSCONFIG'] = [];
    $SysConf['SYSCONFIG']['PasswdPolicy'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicyMinChar'] = '';
    $SysConf['SYSCONFIG']['PasswdPolicyMaxChar'] = 16;
    $SysConf['SYSCONFIG']['PasswdPolicyLower'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicyUpper'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicyDigit'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicySpecial'] = '#%@^!*()';
    $expected = '(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[#%@^!*()])[a-zA-Z\\d#%@^!*()]{0,16}';
    $actual = generate_password_policy();
    $this->assertEquals($expected, $actual);
  }

  /**
   * @brief Test for generate_password_policy() when no max limit is set
   * @test
   * -# Setup SYSCONFIG
   * -# Enable all policy settings
   * -# Set max limit as empty
   * -# Call generate_password_policy() and match expected regex without max
   *    limit
   */
  function test_generate_password_policy_no_max()
  {
    global $SysConf;
    $SysConf = [];
    $SysConf['SYSCONFIG'] = [];
    $SysConf['SYSCONFIG']['PasswdPolicy'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicyMinChar'] = 2;
    $SysConf['SYSCONFIG']['PasswdPolicyMaxChar'] = '';
    $SysConf['SYSCONFIG']['PasswdPolicyLower'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicyUpper'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicyDigit'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicySpecial'] = '#%@^!*()';
    $expected = '(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[#%@^!*()])[a-zA-Z\\d#%@^!*()]{2,}';
    $actual = generate_password_policy();
    $this->assertEquals($expected, $actual);
  }

  /**
   * @brief Test for generate_password_policy()
   * @test
   * -# Setup SYSCONFIG
   * -# Enable all policy settings
   * -# Set min and max limits as empty
   * -# Call generate_password_policy() and match expected regex with * limit
   */
  function test_generate_password_policy_no_limit()
  {
    global $SysConf;
    $SysConf = [];
    $SysConf['SYSCONFIG'] = [];
    $SysConf['SYSCONFIG']['PasswdPolicy'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicyMinChar'] = '';
    $SysConf['SYSCONFIG']['PasswdPolicyMaxChar'] = '';
    $SysConf['SYSCONFIG']['PasswdPolicyLower'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicyUpper'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicyDigit'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicySpecial'] = '#%@^!*()';
    $expected = '(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[#%@^!*()])[a-zA-Z\\d#%@^!*()]*';
    $actual = generate_password_policy();
    $this->assertEquals($expected, $actual);
  }

  /**
   * @brief Test for generate_password_policy()
   * @test
   * -# Setup SYSCONFIG
   * -# Enable all policy settings
   * -# Set special characters as empty
   * -# Call generate_password_policy() and match expected regex to accept any
   *    special character
   */
  function test_generate_password_policy_no_special()
  {
    global $SysConf;
    $SysConf = [];
    $SysConf['SYSCONFIG'] = [];
    $SysConf['SYSCONFIG']['PasswdPolicy'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicyMinChar'] = '8';
    $SysConf['SYSCONFIG']['PasswdPolicyMaxChar'] = '16';
    $SysConf['SYSCONFIG']['PasswdPolicyLower'] = 'false';
    $SysConf['SYSCONFIG']['PasswdPolicyUpper'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicyDigit'] = 'true';
    $SysConf['SYSCONFIG']['PasswdPolicySpecial'] = '';
    $expected = '(?=.*[A-Z])(?=.*\\d).{8,16}';
    $actual = generate_password_policy();
    $this->assertEquals($expected, $actual);
  }
}
