<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/


namespace Fossology\Lib\Util;

class StringOperationTest extends \PHPUnit\Framework\TestCase
{

  protected function setUp() : void
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }

  public function testGetCommonHead()
  {
    assertThat(StringOperation::getCommonHead('abc','abc'), equalTo('abc'));
    assertThat(StringOperation::getCommonHead('abcd','abc'), equalTo('abc'));
    assertThat(StringOperation::getCommonHead('abc','abcd'), equalTo('abc'));
    assertThat(StringOperation::getCommonHead('abcd','abce'), equalTo('abc'));
    assertThat(StringOperation::getCommonHead('abcdf','abcef'), equalTo('abc'));
    assertThat(StringOperation::getCommonHead('abc',''), equalTo(''));
    assertThat(StringOperation::getCommonHead('','abc'), equalTo(''));
  }

  /**
   * @test
   * Test for StringOperation::replaceUnicodeControlChar
   * -# Pass various valid and invalid unicode strings as input
   * -# Pass various replace characters
   * -# Check if the output is free of invalid unichars
   */
  public function testReplaceUnicodeControlChar()
  {
    assertThat(StringOperation::replaceUnicodeControlChar('Y', '?'),
      equalTo('?Y'));
    assertThat(StringOperation::replaceUnicodeControlChar('“IND'),
      equalTo('“IND'));
    assertThat(StringOperation::replaceUnicodeControlChar('y’©', 'a'),
      equalTo('y’©'));
    assertThat(StringOperation::replaceUnicodeControlChar('eys'),
      equalTo('eys'));
    assertThat(StringOperation::replaceUnicodeControlChar('नमस्ते', '.'),
      equalTo('नमस्ते'));
    assertThat(StringOperation::replaceUnicodeControlChar('abc', ''),
      equalTo('abc'));
    assertThat(StringOperation::replaceUnicodeControlChar('ab	c'),
      equalTo('ab	c'));
    assertThat(StringOperation::replaceUnicodeControlChar("abc\r\na", ''),
      equalTo("abc\r\na"));
    assertThat(StringOperation::replaceUnicodeControlChar("ab\tc"),
      equalTo("ab\tc"));
    assertThat(StringOperation::replaceUnicodeControlChar('', 'abc'),
      equalTo(''));
  }
}
