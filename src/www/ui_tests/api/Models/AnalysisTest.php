<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief Unit test cases for API models
 * @file
 * @brief Tests for Analysis
 */

/**
 * @namespace Fossology::UI::Api::Test::Models
 *            Unit tests for models
 */
namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\Analysis;
use Fossology\UI\Api\Models\ApiVersion;

/**
 * @class AnalysisTest
 * @brief Tests for Analysis model
 */
class AnalysisTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @test
   * -# Test for Analysis::setUsingArray() when $version is V1
   * -# Check if the Analysis object is updated with actual array values
   */
  public function testSetUsingArrayV1()
  {
    $this->testSetUsingArray(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test for Analysis::setUsingArray() when $version is V2
   * -# Check if the Analysis object is updated with actual array values
   */
  public function testSetUsingArrayV2()
  {
    $this->testSetUsingArray(ApiVersion::V2);
  }

  /**
   * @param $version version to test
   * @return void
   * -# Test for Analysis::setUsingArray() to check if the Analysis object is updated with actual array values
   */
  private function testSetUsingArray($version)
  {
    if ($version == ApiVersion::V2) {
      $analysisArray = [
        "bucket" => true,
        "copyrightEmailAuthor" => "true",
        "ecc" => 1,
        "keyword" => (1==1),
        "mime" => false,
        "monk" => "false",
        "nomos" => 0,
        "ojo" => (1==2)
      ];
    } else {
      $analysisArray = [
        "bucket" => true,
        "copyright_email_author" => "true",
        "ecc" => 1,
        "keyword" => (1==1),
        "mime" => false,
        "monk" => "false",
        "nomos" => 0,
        "ojo" => (1==2)
      ];
    }

    $expectedObject = new Analysis(true, true, true, true);

    $actualObject = new Analysis();
    $actualObject->setUsingArray($analysisArray, $version);

    $this->assertEquals($expectedObject, $actualObject);
  }

  /**
   * @test
   * -# Test for Analysis::setUsingString()
   * -# Create two strings with different delimiters
   * -# Check if the created Analysis objects hold expected values
   */
  public function testSetUsingString()
  {
    $analysisStringComma = "bucket, ecc, keyword";
    $analysisStringSemi = "bucket;ecc;monk";

    $expectedObjectComma = new Analysis();
    $expectedObjectComma->setBucket(true);
    $expectedObjectComma->setEcc(true);
    $expectedObjectComma->setKeyword(true);

    $expectedObjectSemi = new Analysis();
    $expectedObjectSemi->setBucket(true);
    $expectedObjectSemi->setEcc(true);
    $expectedObjectSemi->setMonk(true);

    $actualObjectComma = new Analysis();
    $actualObjectComma->setUsingString($analysisStringComma);
    $actualObjectSemi = new Analysis();
    $actualObjectSemi->setUsingString($analysisStringSemi);

    $this->assertEquals($expectedObjectComma, $actualObjectComma);
    $this->assertEquals($expectedObjectSemi, $actualObjectSemi);
  }

  /**
   * @test
   * -# Test the data format returned by Analysis::getArray($version) model when $version is V1
   * -# Create expected array
   * -# Create test object and set the values
   * -# Get the array from object and match with expected array
   */
  public function testDataFormatV1()
  {
    $this->testDataFormat(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test the data format returned by Analysis::getArray($version) model when $version is V2
   * -# Create expected array
   * -# Create test object and set the values
   * -# Get the array from object and match with expected array
   */
  public function testDataFormatV2()
  {
    $this->testDataFormat(ApiVersion::V2);
  }

  /**
   * @param $version version to test
   * @return void
   * -# Test the data format returned by Upload::getArray($version) model
   */
  private function testDataFormat($version)
  {
    if($version==ApiVersion::V2){
      $expectedArray = [
        "bucket"    => true,
        "copyrightEmailAuthor" => true,
        "ecc"       => false,
        "keyword"   => false,
        "mimetype"  => true,
        "monk"      => false,
        "nomos"     => true,
        "ojo"       => true,
        "package"   => false,
        "reso"      => true
      ];
    } else{
      $expectedArray = [
        "bucket"    => true,
        "copyright_email_author" => true,
        "ecc"       => false,
        "keyword"   => false,
        "mimetype"  => true,
        "monk"      => false,
        "nomos"     => true,
        "ojo"       => true,
        "package"   => false,
        "reso"      => true
      ];
    }
    $actualObject = new Analysis();
    $actualObject->setBucket(true);
    $actualObject->setCopyright(true);
    $actualObject->setMime(true);
    $actualObject->setNomos(true);
    $actualObject->setOjo(true);
    $actualObject->setReso(true);

    $this->assertEquals($expectedArray, $actualObject->getArray($version));
  }

  ////// New Getter and Setter Tests //////

  /**
   * @test
   * -# Test getter for bucket
   */
  public function testGetBucket()
  {
    $analysis = new Analysis(true);
    $this->assertTrue($analysis->getBucket());
  }

  /**
   * @test
   * -# Test setter for bucket
   */
  public function testSetBucket()
  {
    $analysis = new Analysis();
    $analysis->setBucket(true);
    $this->assertTrue($analysis->getBucket());
  }

  /**
   * @test
   * -# Test getter for copyright
   */
  public function testGetCopyright()
  {
    $analysis = new Analysis(false, true);
    $this->assertTrue($analysis->getCopyright());
  }

  /**
   * @test
   * -# Test setter for copyright
   */
  public function testSetCopyright()
  {
    $analysis = new Analysis();
    $analysis->setCopyright(true);
    $this->assertTrue($analysis->getCopyright());
  }

  /**
   * @test
   * -# Test getter for ecc
   */
  public function testGetEcc()
  {
    $analysis = new Analysis(false, false, true);
    $this->assertTrue($analysis->getEcc());
  }

  /**
   * @test
   * -# Test setter for ecc
   */
  public function testSetEcc()
  {
    $analysis = new Analysis();
    $analysis->setEcc(true);
    $this->assertTrue($analysis->getEcc());
  }

  /**
   * @test
   * -# Test getter for keyword
   */
  public function testGetKeyword()
  {
    $analysis = new Analysis(false, false, false, true);
    $this->assertTrue($analysis->getKeyword());
  }

  /**
   * @test
   * -# Test setter for keyword
   */
  public function testSetKeyword()
  {
    $analysis = new Analysis();
    $analysis->setKeyword(true);
    $this->assertTrue($analysis->getKeyword());
  }

  /**
   * @test
   * -# Test getter for mimetype
   */
  public function testGetMime()
  {
    $analysis = new Analysis(false, false, false, false, true);
    $this->assertTrue($analysis->getMime());
  }

  /**
   * @test
   * -# Test setter for mimetype
   */
  public function testSetMime()
  {
    $analysis = new Analysis();
    $analysis->setMime(true);
    $this->assertTrue($analysis->getMime());
  }

  /**
   * @test
   * -# Test getter for monk
   */
  public function testGetMonk()
  {
    $analysis = new Analysis(false, false, false, false, false, true);
    $this->assertTrue($analysis->getMonk());
  }
}
