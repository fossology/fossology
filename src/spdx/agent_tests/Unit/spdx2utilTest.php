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
namespace Fossology\Spdx;

require_once(__DIR__ . '/../../agent/spdxutils.php');

/**
 * @class spdx2Test
 * @brief Unit tests for SPDX2
 */
class spdx2utilTest extends \PHPUnit\Framework\TestCase
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
    assertThat(SpdxUtils::preWorkOnArgsFlp($args,"key1","key2"), equalTo($args));
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
    assertThat(SpdxUtils::preWorkOnArgsFlp($args,"key1","key2"), equalTo($args));
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
    $result = SpdxUtils::preWorkOnArgsFlp($args,"key1","key2");
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
    assertThat(SpdxUtils::addPrefixOnDemand("LIC1"), equalTo("LIC1"));
  }
}
