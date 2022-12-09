<?php
/*
 SPDX-FileCopyrightText: © 2016 Siemens AG
 SPDX-FileCopyrightText: © 2017 TNG Technology Consulting GmbH

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief Unit test cases for SPDX2 agent
 * @file
 * @brief Unit test cases for SPDX2 agent
 */
namespace Fossology\SpdxTwo;

require_once(__DIR__ . '/../../agent/spdx2utils.php');

/**
 * @class spdx2Test
 * @brief Unit tests for SPDX2
 */
class spdx2Test extends \PHPUnit\Framework\TestCase
{
  private $assertCountBefore;       ///< Assertion count

  /**
   * @brief Setup test env
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  /**
   * @brief Tear down test env
   * @see PHPUnit_Framework_TestCase::tearDown()
   */
  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }

  /**
   * @brief Test preWorkOnArgsFlp() with empty array
   * @test
   * -# Create empty args array
   * -# Call preWorkOnArgsFlp()
   * -# Check if empty array is returned
   */
  public function testPreWorkOnArgsFlpZero()
  {
    $args = array();
    assertThat(SpdxTwoUtils::preWorkOnArgsFlp($args,"key1","key2"), equalTo($args));
  }

  /**
   * @brief Test preWorkOnArgsFlp() with args containing only key1
   * @test
   * -# Create args array with only key1
   * -# Call preWorkOnArgsFlp() with key1 and key2
   * -# Check if args array is not modified
   */
  public function testPreWorkOnArgsFlpId()
  {
    $args = array("key1" => "value");
    assertThat(SpdxTwoUtils::preWorkOnArgsFlp($args,"key1","key2"), equalTo($args));
  }

  /**
   * @brief Test preWorkOnArgsFlp() with actual format
   * @test
   * -# Create args array with key1 containing `--key2=` as a value
   * -# Call preWorkOnArgsFlp() with key1 and key2
   * -# Check if array with proper assignment is returned
   */
  public function testPreWorkOnArgsFlpRealWork()
  {
    $args = array("key1" => "value --key2=anotherValue");
    $result = SpdxTwoUtils::preWorkOnArgsFlp($args,"key1","key2");
    assertThat($result["key1"], equalTo("value"));
    assertThat($result["key2"], equalTo("anotherValue"));
  }

  /**
   * @brief Test addPrefixOnDemand() with no check
   * @test
   * -# Call addPrefixOnDemand() with no check
   * -# Check if original string is returned
   */
  public function testAddPrefixOnDemandNoChecker()
  {
    assertThat(SpdxTwoUtils::addPrefixOnDemand("LIC1"), equalTo("LIC1"));
  }

  /**
   * @brief Prefix provider for licenses
   * @return string[][]
   */
  public function provideLicenseSetAddPrefixOnDemand()
  {
    $constTrue = function ($licId){
      return true;
    };
    $constFalse = function ($licId){
      return false;
    };

    return array(
        'null' => array("LIC1", null, "LIC1"),
        'const false' => array("LIC1", $constFalse, SpdxTwoUtils::$prefix . "LIC1"),
        'const true' => array("LIC1", $constTrue, "LIC1"),
        'invalid plus' => array("abc+123", $constFalse, SpdxTwoUtils::$prefix . "abc-123"),
        'other invalid chars' => array("to do?", $constFalse, SpdxTwoUtils::$prefix . "to-do-"),
        'valid plus' => array("this+that_more+", $constFalse, SpdxTwoUtils::$prefix . "this-that_more+"),
        'valid periods' => array("name.with.dots.", $constFalse, SpdxTwoUtils::$prefix . "name.with.dots.")
    );
  }

  /**
   * @brief Test addPrefixOnDemand()
   * @dataProvider provideLicenseSetAddPrefixOnDemand
   * @param string $licenseId
   * @param callable $checker
   * @param string $expected
   */
  public function testAddPrefixOnDemand($licenseId, $checker, $expected)
  {
    assertThat(SpdxTwoUtils::addPrefixOnDemand($licenseId, $checker), equalTo($expected));
  }

  /**
   * @brief Prefix provider for license list
   * @return string[][]
   */
  public function provideLicenseSetAddPrefixOnDemandList()
  {
    $constTrue = function ($licId)
    {
      return true;
    };
    $constFalse = function ($licId)
    {
      return false;
    };

    return array(
        'single with null' => array(array("LIC1"), null, array("LIC1")),
        'single with const false' => array(array("LIC1"), $constFalse, array(SpdxTwoUtils::$prefix . "LIC1")),
        'single with const true' => array(array("LIC1"), $constTrue, array("LIC1")),
        'multiple with null' => array(array("LIC1","LIC2","LIC3"), null, array("LIC1", "LIC2", "LIC3")),
        'multiple with const false' => array(array("LIC1","LIC2","LIC3"),
            $constFalse, array(SpdxTwoUtils::$prefix . "LIC1", SpdxTwoUtils::$prefix
            . "LIC2", SpdxTwoUtils::$prefix . "LIC3")),
        'multiple with const true' => array(array("LIC1","LIC2","LIC3"), $constTrue, array("LIC1","LIC2","LIC3")),
        'two licenses with one prefix (A)' => array(array("LIC1","LIC2"),
          function ($licId)
          {
            return $licId === 'LIC2';
          }, array(SpdxTwoUtils::$prefix.'LIC1', 'LIC2')),
        'two licenses with one prefix (2)' => array(array("LIC1","LIC2"),
          function ($licId)
          {
            return $licId === 'LIC1';
          }, array('LIC1', SpdxTwoUtils::$prefix.'LIC2'))
    );
  }

  /**
   * @brief Test for addPrefixOnDemandList()
   * @dataProvider provideLicenseSetAddPrefixOnDemandList
   * @param array $licenseIds
   * @param callable $checker
   * @param string $expected
   */
  public function testAddPrefixOnDemandList($licenseIds, $checker, $expected)
  {
    assertThat(SpdxTwoUtils::addPrefixOnDemandList($licenseIds, $checker), equalTo($expected));
  }

  /**
   * @brief Prefix provider for license implode
   * @return string[][]
   */
  public function provideLicenseSetImplodeLicenses()
  {
    $constTrue = function ($licId)
    {
      return true;
    };
    $constFalse = function ($licId)
    {
      return false;
    };

    return array(
        'null' => array(null, $constTrue,  ''),
        'empty array' => array(array(), $constTrue, ''),
        'empty array but prefix' => array(array(), $constFalse, ''),
        'single license' => array(array("LIC1"), $constTrue, 'LIC1'),
        'multiple licenses' => array(array("LIC1","LIC2","LIC3"), $constTrue, 'LIC1 AND LIC2 AND LIC3'),
        'dual license 1st pos' => array(array("Dual-license", "LIC2", "LIC3"), $constTrue, 'LIC2 OR LIC3'),
        'dual license 2nd pos' => array(array("LIC1", "Dual-license", "LIC3"), $constTrue, 'LIC1 OR LIC3'),
        'dual license 3rd pos' => array(array("LIC1", "LIC2", "Dual-license"), $constTrue,  'LIC1 OR LIC2'),
        'dual license with prefix' => array(array("LIC1","LIC2", "Dual-license"),
            function ($licId)
            {
              return $licId === 'LIC2';
            }, SpdxTwoUtils::$prefix.'LIC1 OR LIC2'),
        'dual license with prefix' => array(array("LIC1","LIC2", "Dual-license"),
            function ($licId)
            {
              return $licId === 'LIC1';
            }, 'LIC1 OR '.SpdxTwoUtils::$prefix.'LIC2'),
        'dual license with prefix ($constFalse)' => array(array("LIC1","LIC2", "Dual-license"),
            $constFalse, SpdxTwoUtils::$prefix.'LIC1 OR '.SpdxTwoUtils::$prefix.'LIC2'),
        'multiple dualLicense' => array(array("LIC1","LIC2 OR LIC3"),  $constTrue, '(LIC2 OR LIC3) AND LIC1'),
        'multiple dualLicense with prefix' => array(array("LIC1","LIC2 OR LIC3"),
            $constFalse, '(LIC2 OR LIC3) AND '.SpdxTwoUtils::$prefix.'LIC1'),
        'dual multi license' => array(array("LIC1","LIC2 OR LIC3", "Dual-license"), $constTrue, '(LIC2 OR LIC3) OR LIC1'),
        'dual multi license with prefix' => array(array("LIC1","LIC2 OR LIC3", "Dual-license"),
            $constFalse, '(LIC2 OR LIC3) OR '.SpdxTwoUtils::$prefix.'LIC1'),
    );
  }

  /**
   * @brief Test implodeLicenses()
   * @dataProvider provideLicenseSetImplodeLicenses
   * @param array $lics
   * @param string $prefix
   * @param string $expected
   */
  public function testImplodeLicenses($lics, $prefix, $expected)
  {
    assertThat(SpdxTwoUtils::implodeLicenses($lics, $prefix), equalTo($expected));
  }
}
