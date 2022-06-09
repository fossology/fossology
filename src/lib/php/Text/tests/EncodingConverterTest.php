<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Text;

class EncodingConverterTest extends \PHPUnit\Framework\TestCase
{

  private $testString = "äöüßÄÖÜ";

  /** @var EncodingConverter */
  private $converter;

  protected function setUp() : void
  {
    $detected = mb_detect_encoding($this->testString);
    assertThat($detected, is("UTF-8"));
    $this->converter = new EncodingConverter();
  }

  public function testUtf8IsKept()
  {
    assertThat( $this->converter->convert($this->testString), is($this->testString));
  }

  public function testLatin15IsConverted()
  {
    $encodedString = iconv("UTF-8", "ISO-8859-15", $this->testString);
    assertThat( $this->converter->convert($encodedString), is($this->testString));
  }

  public function testMixedEncodingIsConvertedAndCoercedToUtf8()
  {
    $inputString = $this->testString;
    $inputString .= iconv("UTF-8", "ISO-8859-15", $this->testString);
    $outputString = $this->converter->convert($inputString);
    assertThat( $outputString, endsWith($this->testString));
    assertThat( strlen($outputString), is(greaterThan(2 * strlen($this->testString))));
  }

  public function testMixedEncodingStartingWithLatin1IsConvertedAndCoercedToUtf8()
  {
    $inputString = iconv("UTF-8", "ISO-8859-15", $this->testString);
    $inputString .= $this->testString;
    $outputString = $this->converter->convert($inputString);
    assertThat( $outputString, startsWith($this->testString));
    assertThat( strlen($outputString), is(greaterThan(2 * strlen($this->testString))));
  }
}
