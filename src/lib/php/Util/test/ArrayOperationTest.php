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

namespace Fossology\Lib\Util;


class ArrayOperationTest extends \PHPUnit_Framework_TestCase
{
  
  public function setUp()
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  public function tearDown() {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }

  public function testCallChunked()
  {
    $values = array(1, 2, 3);

    assertThat(ArrayOperation::callChunked(function ($values)
    {
      return array(count($values));
    }, $values, 2), is(array(2, 1)));
  }

  public function testCallChunkedWithNoValues()
  {
    $values = array();

    assertThat(ArrayOperation::callChunked(function ($values)
    {
      return array(count($values));
    }, $values, 2), is(emptyArray()));
  }

  public function testCallChunkedWithValueSizeSmallerThanChunkLimit()
  {
    $values = array(1, 2, 3);

    assertThat(ArrayOperation::callChunked(function ($values)
    {
      return array(count($values));
    }, $values, 5), is(array(3)));
  }

  public function testCallChunkedWithExactMultiple()
  {
    $values = array(1, 2, 3, 4);

    assertThat(ArrayOperation::callChunked(function ($values)
    {
      return array(count($values));
    }, $values, 2), is(array(2, 2)));
  }

  /** @expectedException \InvalidArgumentException
   * @expectedExceptionMessage chunk size should be positive
   */
  public function testCallChunkedShouldThrowExceptionWhenChunkSizeIsNotPositive() {
    ArrayOperation::callChunked(function ($values)
    {
      return array(count($values));
    }, array(), 0);
  }
  
  public function testMultiSearch()
  {
    $haystack = array(100, 101, 102, 101);
    assertThat(ArrayOperation::multiSearch(array(100),$haystack),is(0));
    assertThat(ArrayOperation::multiSearch(array(101),$haystack),is(1));
    assertThat(ArrayOperation::multiSearch(array(100,102),$haystack),is(0));
    assertThat(ArrayOperation::multiSearch(array(200),$haystack),is(false));
    assertThat(ArrayOperation::multiSearch(array(200,102),$haystack),is(2));
  }
  
}
 