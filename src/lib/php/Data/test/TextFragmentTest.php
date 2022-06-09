<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

class TextFragmentTest extends \PHPUnit\Framework\TestCase
{
  const START_OFFSET = 10;

  const CONTENT = "foo bar baz";

  /**
   * @var TextFragment
   */
  private $fragment;

  protected function setUp() : void
  {
    $this->fragment = new TextFragment(self::START_OFFSET, self::CONTENT);
  }

  public function testGetStartOffset()
  {
    assertThat($this->fragment->getStartOffset(), is(self::START_OFFSET));
  }

  public function testGetEndOffset()
  {
    assertThat($this->fragment->getEndOffset(), is(self::START_OFFSET + 11));
  }

  public function testGetSliceRegular()
  {
    assertThat($this->fragment->getSlice(self::START_OFFSET, self::START_OFFSET + 3), is("foo"));
    assertThat($this->fragment->getSlice(self::START_OFFSET + 4, self::START_OFFSET + 4 + 3), is("bar"));
    assertThat($this->fragment->getSlice(self::START_OFFSET + 8, self::START_OFFSET + 8 + 3), is("baz"));
  }

  public function testGetSliceWithoutEnd()
  {
    assertThat($this->fragment->getSlice(self::START_OFFSET + 8), is("baz"));
  }

  public function testGetSliceAtLeftEdge()
  {
    assertThat($this->fragment->getSlice(self::START_OFFSET - 1, self::START_OFFSET - 1 + 3), is("fo"));
  }

  public function testGetSliceAtRightEdge()
  {
    assertThat($this->fragment->getSlice(self::START_OFFSET + 9, self::START_OFFSET + 9 + 3), is("az"));
  }
}
