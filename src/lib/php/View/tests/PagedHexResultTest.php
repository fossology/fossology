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

use Mockery as M;

class PagedHexResultTest extends \PHPUnit_Framework_TestCase
{

  const START_OFFSET = 5;

  /**
   * @var PagedHexResult
   */
  private $result;

  public function setUp()
  {
    $highlightState = M::mock(HighlightState::className());
    $highlightState->shouldReceive("openExistingElements")->withAnyArgs()->andReturn("");
    $highlightState->shouldReceive("closeOpenElements")->withAnyArgs()->andReturn("");
    $this->result = new PagedHexResult(self::START_OFFSET, $highlightState);
  }

  function tearDown()
  {
    M::close();
  }

  public function testAddSmallAmountOfText()
  {
    $this->result->appendContentText("foo bar");

    assertThat(
        $this->result->getText(),
        is("0x00000005 |66 6f 6f 20 62 61 72 __ __ __ __ __ __ __ __ __| |foo&nbsp;bar&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;|"));
  }

  public function testAddOneHexLineOfText()
  {
    $this->result->appendContentText("foo bar baz done");

    assertThat(
        $this->result->getText(),
        is("0x00000005 |66 6f 6f 20 62 61 72 20 62 61 7a 20 64 6f 6e 65| |foo&nbsp;bar&nbsp;baz&nbsp;done|<br/>\n"));
  }

  public function testAddMoreThanOneHexLineOfText()
  {
    $this->result->appendContentText("foo bar baz donefoo bar");

    assertThat(
        $this->result->getText(),
        is("0x00000005 |66 6f 6f 20 62 61 72 20 62 61 7a 20 64 6f 6e 65| |foo&nbsp;bar&nbsp;baz&nbsp;done|<br/>\n" .
            "0x00000015 |66 6f 6f 20 62 61 72 __ __ __ __ __ __ __ __ __| |foo&nbsp;bar&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;|"));
  }

  public function testAddMetaText()
  {
    $this->result->appendContentText("foo ");
    $this->result->appendMetaText("<b>");
    $this->result->appendContentText("bar");
    $this->result->appendMetaText("</b>");
    $this->result->appendContentText("baz done");

    assertThat(
        $this->result->getText(),
        is("0x00000005 |66 6f 6f 20 <b>62 61 72 </b>62 61 7a 20 64 6f 6e 65 __| |foo&nbsp;<b>bar</b>baz&nbsp;done&nbsp;|"));
  }
}
 