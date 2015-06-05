<?php
/*
Copyright (C) 2014, Siemens AG

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

namespace Fossology\Lib\Text;


class EncodingConverterTest extends \PHPUnit_Framework_TestCase
{

  private $testString = "äöüßÄÖÜ";

  /** @var EncodingConverter */
  private $converter;

  public function setUp()
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
 