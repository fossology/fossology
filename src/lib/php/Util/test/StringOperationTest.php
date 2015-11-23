<?php
/*
Copyright (C) 2015, Siemens AG

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

namespace Fossology\Lib\Util;


class StringOperationTest extends \PHPUnit_Framework_TestCase
{
  
  protected function setUp()
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown()
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
  
}
 