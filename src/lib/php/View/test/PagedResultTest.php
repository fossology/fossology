<?php
/*
Copyright (C) 2014, Siemens AG
Author: Andreas Würl

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

namespace Fossology\Lib\View;

class TestPagedResult extends PagedResult {

  /**
   * @param string $text
   * @return string
   */
  protected function renderContentText($text)
  {
    return $text;
  }
}

class PagedResultTest extends \PHPUnit_Framework_TestCase {

  const START_OFFSET = 12;
  const META_TEXT = "<meta>";
  const CONTENT_TEXT = "<content>";

  /** @var TestPagedResult */
  private $pagedResult;

  public function setUp()
  {
    $this->pagedResult = new TestPagedResult(self::START_OFFSET);
  }

  public function testGetStartOffset()
  {
    assertThat($this->pagedResult->getStartOffset(), is(self::START_OFFSET));
  }

  public function testIsEmpty()
  {
    $this->assertTrue($this->pagedResult->isEmpty());

    $this->pagedResult->appendMetaText(self::META_TEXT);

    $this->assertTrue($this->pagedResult->isEmpty());

    $this->pagedResult->appendContentText(self::CONTENT_TEXT);

    $this->assertFalse($this->pagedResult->isEmpty());
  }

  public function testAppendMetaText()
  {
    $this->pagedResult->appendMetaText(self::META_TEXT);

    assertThat($this->pagedResult->getCurrentOffset(), is(self::START_OFFSET));
    assertThat($this->pagedResult->getText(), is(self::META_TEXT));
  }

  public function testAppendContentText()
  {
    $this->pagedResult->appendContentText(self::CONTENT_TEXT);

    assertThat($this->pagedResult->getCurrentOffset(), is(self::START_OFFSET + strlen(self::CONTENT_TEXT)));
    assertThat($this->pagedResult->getText(), is(self::CONTENT_TEXT));
  }

  public function testAppendContentAndMetaText()
  {
    $this->pagedResult->appendContentText(self::CONTENT_TEXT);
    $this->pagedResult->appendMetaText(self::META_TEXT);

    assertThat($this->pagedResult->getCurrentOffset(), is(self::START_OFFSET + strlen(self::CONTENT_TEXT)));
    assertThat($this->pagedResult->getText(), is(self::CONTENT_TEXT . self::META_TEXT));
  }

  public function testAppendMetaAndContentText()
  {
    $this->pagedResult->appendMetaText(self::META_TEXT);
    $this->pagedResult->appendContentText(self::CONTENT_TEXT);

    assertThat($this->pagedResult->getCurrentOffset(), is(self::START_OFFSET + strlen(self::CONTENT_TEXT)));
    assertThat($this->pagedResult->getText(), is(self::META_TEXT . self::CONTENT_TEXT));
  }

}
 