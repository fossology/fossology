<?php

/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Scancode;
use PHPUnit\Framework\TestCase;

/**
 * @file
 * @brief Tests for ScanCode model
 */
class ScanCodeTest extends TestCase
{
  /**
   * Provides test data and instances of the Scancode class.
   * @return array An associative array containing test data and Scancode objects.
   */
  private function getScanCodeInfo()
  {
    return [
      'scanCodeInfo' => [
        'license' => true,
        'copyright' => true,
        'email' => true,
        'url' => false
      ],
      'obj' => new Scancode(true, true, true, false)
    ];
  }

  /**
   * Test Scancode::getArray()
   * Tests that the Scancode object's getArray method returns the correct data format.
   */
  public function testDataFormat()
  {
    $data = $this->getScanCodeInfo();
    $expectedArray = $data['scanCodeInfo'];
    $scanCode = $data['obj'];
    $this->assertEquals($expectedArray, $scanCode->getArray());
  }

  /**
   * Test Scancode::setUsingArray()
   * Tests the setUsingArray method of the Scancode class.
   * - # Check if the Scancode object properties are correctly set using an associative array.
   */
  public function testSetUsingArray()
  {
    $scanCode = new Scancode();
    $arrayData = [
      'license' => true,
      'copyright' => true,
      'email' => false,
      'url' => true
    ];
    $scanCode->setUsingArray($arrayData);
    $this->assertEquals($arrayData, $scanCode->getArray());
  }

  /**
   * Test Scancode::setScanLicense()
   * Tests the setScanLicense method of the Scancode class.
   * - # Check if the scanLicense property is correctly set.
   */
  public function testSetScanLicense()
  {
    $scanCode = new Scancode();
    $scanCode->setScanLicense(true);
    $this->assertTrue($scanCode->getScanLicense());
  }

  /**
   * Test Scancode::setScanCopyright()
   * Tests the setScanCopyright method of the Scancode class.
   * - # Check if the scanCopyright property is correctly set.
   */
  public function testSetScanCopyright()
  {
    $scanCode = new Scancode();
    $scanCode->setScanCopyright(true);
    $this->assertTrue($scanCode->getScanCopyright());
  }

  /**
   * Test Scancode::setScanEmail()
   * Tests the setScanEmail method of the Scancode class.
   * - # Check if the scanEmail property is correctly set.
   */
  public function testSetScanEmail()
  {
    $scanCode = new Scancode();
    $scanCode->setScanEmail(true);
    $this->assertTrue($scanCode->getScanEmail());
  }

  /**
   * Test Scancode::setScanUrl()
   * Tests the setScanUrl method of the Scancode class.
   * - # Check if the scanUrl property is correctly set.
   */
  public function testSetScanUrl()
  {
    $scanCode = $this->getScanCodeInfo()['obj'];
    $scanCode->setScanUrl(true);
    $this->assertTrue($scanCode->getScanUrl());
  }

  /**
   * Test Scancode::getScanLicense()
   * Tests the getScanLicense method of the Scancode class.
   * - # Check if getScanLicense returns the correct value.
   */
  public function testGetScanLicense()
  {
    $scanCode = $this->getScanCodeInfo()['obj'];
    $this->assertTrue($scanCode->getScanLicense());
  }

  /**
   * Test Scancode::getScanCopyright()
   * Tests the getScanCopyright method of the Scancode class.
   * - # Check if getScanCopyright returns the correct value.
   */
  public function testGetScanCopyright()
  {
    $scanCode = new Scancode(false, true, false, false);
    $this->assertTrue($scanCode->getScanCopyright());
  }

  /**
   * Test Scancode::getScanEmail()
   * Tests the getScanEmail method of the Scancode class.
   * - # Check if getScanEmail returns the correct value.
   */
  public function testGetScanEmail()
  {
    $scanCode = $this->getScanCodeInfo()['obj'];
    $this->assertTrue($scanCode->getScanEmail());
  }

  /**
   * Test Scancode::getScanUrl()
   * Tests the getScanUrl method of the Scancode class.
   * - # Check if getScanUrl returns the correct value.
   */
  public function testGetScanUrl()
  {
    $scanCode = new Scancode(false, false, false, true);
    $this->assertTrue($scanCode->getScanUrl());
  }

  /**
   * Test Scancode::getArray()
   * Tests the getArray method of the Scancode class.
   * - # Check if getArray returns the correct associative array representation.
   */
  public function testGetArray()
  {
    $scanCode = new Scancode(true, false, true, false);
    $expectedArray = [
      'license' => true,
      'copyright' => false,
      'email' => true,
      'url' => false
    ];
    $this->assertEquals($expectedArray, $scanCode->getArray());
  }
}
