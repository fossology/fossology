<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for Reueser model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Reuser;
use Fossology\UI\Api\Models\ApiVersion;

/**
 * @class ReuserTest
 * @brief Tests for Reuser model
 */
class ReuserTest extends \PHPUnit\Framework\TestCase
{
  ////// Constructor Tests //////

  /**
   * Tests that the Reuser constructor initializes an instance correctly.
   *
   * @return void
   */
  public function testConstructor()
  {
    $reuser = new Reuser(2, 'fossy', true);
    $this->assertInstanceOf(Reuser::class, $reuser);
  }

  /**
   * @test
   * -# Test constructor and Reuser::getArray()
   */
  public function testReuserConst()
  {
    $expectedArray = [
      "reuse_upload"     => 2,
      "reuse_group"      => 'fossy',
      "reuse_main"       => true,
      "reuse_enhanced"   => false,
      "reuse_copyright"  => false,
      "reuse_report"     => false,
      "auto_select_reuse" => false
    ];

    $actualReuser = new Reuser(2, 'fossy', true);

    $this->assertEquals($expectedArray, $actualReuser->getArray());
  }

  /**
   * @test
   * -# Test if UnexpectedValueException is thrown for invalid upload and group
   *    id by constructor
   */
  public function testReuserException()
  {
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage("reuse_upload should be integer or array of integers");
    $object = new Reuser('alpha', 2);
  }

  /**
   * @test
   * -# Test constructor with array of upload IDs
   */
  public function testReuserMultipleUploads()
  {
    $expectedArray = [
      "reuse_upload"    => [2, 5, 10],
      "reuse_group"     => 'fossy',
      "reuse_main"      => true,
      "reuse_enhanced"  => false,
      "reuse_copyright" => false,
      "reuse_report"    => false,
      "auto_select_reuse" => false
    ];

    $actualReuser = new Reuser([2, 5, 10], 'fossy', true);

    $this->assertEquals($expectedArray, $actualReuser->getArray());
    $this->assertEquals([2, 5, 10], $actualReuser->getReuseUploads());
  }

  /**
   * @test
   * -# Test getReuseUploads returns array for single upload
   */
  public function testReuseUploadsSingle()
  {
    $reuser = new Reuser(3, 'fossy');
    $this->assertEquals([3], $reuser->getReuseUploads());
  }

  /**
   * @test
   * -# Test constructor with empty array throws exception
   */
  public function testReuserExceptionEmptyArray()
  {
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage("reuse_upload should be integer or array of integers");
    $object = new Reuser([], 'fossy');
  }

  /**
   * @test
   * -# Test constructor with array containing non-numeric throws exception
   */
  public function testReuserExceptionInvalidArray()
  {
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage("reuse_upload should be integer or array of integers");
    $object = new Reuser([1, 'bad', 3], 'fossy');
  }

  /**
   * @test
   * -# Test for Reuser::setUsingArray() when $version is V1
   * -# Check if the Reuser object is updated with actual array values
   */
  public function testSetUsingArrayV1()
  {
    $this->testSetUsingArray(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test for Reuser::setUsingArray() when $version is V2
   * -# Check if the Reuser object is updated with actual array values
   */
  public function testSetUsingArrayV2()
  {
    $this->testSetUsingArray(ApiVersion::V2);
  }
  
  /**
   * @param $version version to test
   * @return void
   * -# Test for Reuser::setUsingArray() to check if the Reuser object is updated with actual array values
   */
  private function testSetUsingArray($version)
  {
    if ($version == ApiVersion::V1) {
      $expectedArray = [
        "reuse_upload"     => 2,
        "reuse_group"      => 'fossy',
        "reuse_main"       => 'true',
        "reuse_enhanced"   => false,
        "reuse_copyright"  => false,
        "reuse_report"     => false
      ];
    } else {
      $expectedArray = [
        "reuseUpload"     => 2,
        "reuseGroup"      => 'fossy',
        "reuseMain"       => 'true',
        "reuseEnhanced"   => false,
        "reuseCopyright"  => false,
        "reuseReport"     => false
      ];
    }

    $actualReuser = new Reuser(1, 'fossy');
    $actualReuser->setUsingArray($expectedArray, $version);

    $expectedArray[$version == ApiVersion::V1? "reuse_main" : "reuseMain"] = true;
    if ($version == ApiVersion::V1) {
      $expectedArray["auto_select_reuse"] = false;
    } else {
      $expectedArray["autoSelectReuse"] = false;
    }
    $this->assertEquals($expectedArray, $actualReuser->getArray($version));
  }

  /**
   * @test
   * -# Test for Reuser::setUsingArray()
   * -# Add some changes to the array.
   */
  public function testSetUsingArraySomeOptions()
  {
    $expectedArray = [
      "reuse_upload"   => 2,
      "reuse_group"    => 'fossy',
      "reuse_main"     => 'true',
      "reuse_enhanced" => false,
      "reuse_copyright" => 'true',
      "reuse_report"   => false
    ];

    $actualReuser = new Reuser(1, 'fossy');
    $actualReuser->setUsingArray($expectedArray);

    $expectedArray["reuse_main"] = true;
    $expectedArray["reuse_copyright"] = true;
    $expectedArray["auto_select_reuse"] = false;
    $this->assertEquals($expectedArray, $actualReuser->getArray());
  }

  /**
   * @test
   * -# Test if UnexpectedValueException is thrown for invalid upload and group
   *    id by Reuser::setUsingArray()
   */
  public function testSetUsingArrayException()
  {
    $expectedArray = [
      "reuse_upload"   => 'alpha',
      "reuse_group"    => 'fossy',
      "reuse_main"     => 'true',
      "reuse_enhanced" => false
    ];

    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage("Reuse upload should be an integer or array of integers!");

    $actualReuser = new Reuser(1, 'fossy');
    $actualReuser->setUsingArray($expectedArray);
  }

  /**
   * @test
   * -# Test setUsingArray with array of upload IDs (V1)
   */
  public function testSetUsingArrayMultipleUploadsV1()
  {
    $inputArray = [
      "reuse_upload"   => [2, 5, 10],
      "reuse_group"    => 'fossy',
      "reuse_main"     => 'true',
      "reuse_enhanced" => false,
      "reuse_copyright" => false,
      "reuse_report"   => false
    ];

    $reuser = new Reuser(1, 'fossy');
    $reuser->setUsingArray($inputArray, ApiVersion::V1);

    $this->assertEquals([2, 5, 10], $reuser->getReuseUpload());
    $this->assertEquals([2, 5, 10], $reuser->getReuseUploads());
  }

  /**
   * @test
   * -# Test setUsingArray with array of upload IDs (V2)
   */
  public function testSetUsingArrayMultipleUploadsV2()
  {
    $inputArray = [
      "reuseUpload"   => [2, 5, 10],
      "reuseGroup"    => 'fossy',
      "reuseMain"     => 'true',
      "reuseEnhanced" => false,
      "reuseCopyright" => false,
      "reuseReport"   => false
    ];

    $reuser = new Reuser(1, 'fossy');
    $reuser->setUsingArray($inputArray, ApiVersion::V2);

    $this->assertEquals([2, 5, 10], $reuser->getReuseUpload());
    $this->assertEquals([2, 5, 10], $reuser->getReuseUploads());
  }
}
