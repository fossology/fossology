<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\View;

use Mockery as M;

class PagedHexResultTest extends \PHPUnit\Framework\TestCase
{

  const START_OFFSET = 5;

  /**
   * @var PagedHexResult
   */
  private $result;

  protected function setUp() : void
  {
    $highlightState = M::mock(HighlightState::class);
    $highlightState->shouldReceive("openExistingElements")->withAnyArgs()->andReturn("");
    $highlightState->shouldReceive("closeOpenElements")->withAnyArgs()->andReturn("");
    $this->result = new PagedHexResult(self::START_OFFSET, $highlightState);
  }

  protected function tearDown() : void
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
