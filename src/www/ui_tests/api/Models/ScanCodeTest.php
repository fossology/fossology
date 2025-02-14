<?php
/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for ScanCode model
 */
namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Scancode;
use Monolog\Test\TestCase;

class ScanCodeTest extends TestCase
{
  ////// Constructor Tests //////

  /**
   * Tests that the ScanCode constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $scanCode = new ScanCode();
    $this->assertInstanceOf(ScanCode::class, $scanCode);
  }

  /**
   * Provides test data and an instance of the ScanCode class.
   *
   * @return array An associative array containing:
   *  - `expectedArray`: The expected array structure.
   *  - `obj`: The instance of ScanCode being tested.
   */
  private function getScanCodeInfo()
  {
    $expectedArray = [
      "license"  => false,
      "copyright" => false,
      "email" => false,
      "url" => false
    ];
    $obj = new Scancode();
    return [
      'expectedArray' => $expectedArray,
      'obj' => $obj
    ];
  }

  /**
   * Tests ScanCode::getArray() method.
   *
   * This method validates that the `getArray` method returns the correct data structure.
   */
  public function testDataFormat()
  {
    $expectedArray = $this->getScanCodeInfo()['expectedArray'];
    $scanCode = $this->getScanCodeInfo()['obj'];
    $this->assertEquals($expectedArray, $scanCode->getArray());
  }

  /**
   * Tests ScanCode::setLicense() and getLicense() methods.
   *
   * This method verifies that the `setLicense` method correctly updates the license property,
   * and that the `getLicense` method returns the updated value.
   */
  public function testSetAndGetLicense()
  {
    $info = $this->getScanCodeInfo();
    $obj = $info['obj'];
    $obj->setScanLicense(true);
    $this->assertTrue($obj->getScanLicense());
  }

  /**
   * Tests ScanCode::setCopyright() and getCopyright() methods.
   *
   * This method verifies that the `setCopyright` method correctly updates the copyright property,
   * and that the `getCopyright` method returns the updated value.
   */
  public function testSetAndGetCopyright()
  {
    $info = $this->getScanCodeInfo();
    $obj = $info['obj'];
    $obj->setScanCopyright(true);
    $this->assertTrue($obj->getScanCopyright());
  }

  /**
   * Tests ScanCode::setEmail() and getEmail() methods.
   *
   * This method verifies that the `setEmail` method correctly updates the email property,
   * and that the `getEmail` method returns the updated value.
   */
  public function testSetAndGetEmail()
  {
    $info = $this->getScanCodeInfo();
    $obj = $info['obj'];
    $obj->setScanEmail(true);
    $this->assertTrue($obj->getScanEmail());
  }

  /**
   * Tests ScanCode::setUrl() and getUrl() methods.
   *
   * This method verifies that the `setUrl` method correctly updates the url property,
   * and that the `getUrl` method returns the updated value.
   */
  public function testSetAndGetUrl()
  {
    $info = $this->getScanCodeInfo();
    $obj = $info['obj'];
    $obj->setScanUrl(true);
    $this->assertTrue($obj->getScanUrl());
  }
}
