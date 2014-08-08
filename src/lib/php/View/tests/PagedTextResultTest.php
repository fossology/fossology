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


class PagedTextResultTest extends \PHPUnit_Framework_TestCase {

  const START_OFFSET = 15;

  /** @var PagedTextResult */
  private $pagedTextResult;

  public function setUp()
  {
    $this->pagedTextResult = new PagedTextResult(self::START_OFFSET);
  }

  public function testRenderContentTextEscapesForHtml()
  {
    $this->pagedTextResult->appendContentText("&");

    assertThat($this->pagedTextResult->getText(), is("&amp;"));
  }

  public function testRenderContentTextConvertsToUtf8()
  {
    $this->pagedTextResult->appendContentText("äöü");

    assertThat($this->pagedTextResult->getText(), is(mb_convert_encoding("äöü", "UTF-8")));
  }

}
 