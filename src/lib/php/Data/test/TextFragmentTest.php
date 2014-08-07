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

namespace Fossology\Lib\Data;


class TextFragmentTest extends \PHPUnit_Framework_TestCase
{
  const START_OFFSET = 10;

  const CONTENT = "foo bar baz";

  /**
   * @var TextFragment
   */
  private $fragment;

  public function setUp()
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
 