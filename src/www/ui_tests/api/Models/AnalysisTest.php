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

/**
 * @class AnalysisTest
 * @brief Tests for Analysis model
 */
class AnalysisTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @test
   * -# Test for Analysis::setUsingArray()
   * -# Check if the Analysis object is updated with actual array values
   */
  public function testSetUsingArray()
  {
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

    $expectedObject = new Analysis(true, true, true, true);

    $actualObject = new Analysis();
    $actualObject->setUsingArray($analysisArray);

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
   * -# Test for Analysis::getArray()
   * -# Create expected array
   * -# Create test object and set the values
   * -# Get the array from object and match with expected array
   */
  public function testDataFormat()
  {
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
    $actualObject = new Analysis();
    $actualObject->setBucket(true);
    $actualObject->setCopyright(true);
    $actualObject->setMime(true);
    $actualObject->setNomos(true);
    $actualObject->setOjo(true);
    $actualObject->setReso(true);

    $this->assertEquals($expectedArray, $actualObject->getArray());
  }
}
