<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Util;

class ArrayOperationTest extends \PHPUnit\Framework\TestCase
{

  protected function setUp() : void
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
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

  public function testCallChunkedShouldThrowExceptionWhenChunkSizeIsNotPositive()
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("chunk size should be positive");
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

  public function testArrayKeyExists()
  {
    $haystack = ["Key1" => "Value1", "Key2" => "Value2", "Key3" => "Value3"];
    $this->assertTrue(ArrayOperation::arrayKeysExists($haystack, ["Key1", "Key2", "Key3"]));
    $this->assertTrue(ArrayOperation::arrayKeysExists($haystack, ["Key1", "Key2"]));
    $this->assertTrue(ArrayOperation::arrayKeysExists($haystack, ["Key3"]));
    $this->assertFalse(ArrayOperation::arrayKeysExists($haystack, ["Key11", "Key2", "Key3"]));
    $this->assertFalse(ArrayOperation::arrayKeysExists($haystack, ["Key11"]));
  }
}
