<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\View;

use Mockery as M;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\SplitPosition;

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

  /**
   * @dataProvider trailingZeroProvider
   */
  public function testTrailingZero(array $chunks, $expectedText)
  {
    foreach ($chunks as $chunk) {
      $this->result->appendContentText($chunk);
    }

    $this->assertSame($expectedText, $this->result->getText());
  }

  public static function trailingZeroProvider()
  {
    $fullRow = " |41 41 41 41 41 41 41 41 41 41 41 41 41 41 41 41| |AAAAAAAAAAAAAAAA|<br/>\n";
    $zeroRow = " |30 __ __ __ __ __ __ __ __ __ __ __ __ __ __ __| |0" .
      str_repeat("&nbsp;", 15) . "|";
    $twoRows = "0x00000005" . $fullRow . "0x00000015" . $zeroRow;

    return array(
      '16 bytes' => array(array(str_repeat('A', 15) . '0'),
        "0x00000005 |41 41 41 41 41 41 41 41 41 41 41 41 41 41 41 30| |AAAAAAAAAAAAAAA0|<br/>\n"),
      '17 bytes' => array(array(str_repeat('A', 16) . '0'), $twoRows),
      '18 bytes' => array(array(str_repeat('A', 17) . '0'),
        "0x00000005" . $fullRow .
        "0x00000015 |41 30 __ __ __ __ __ __ __ __ __ __ __ __ __ __| |A0" .
        str_repeat("&nbsp;", 14) . "|"),
      '33 bytes' => array(array(str_repeat('A', 32) . '0'),
        "0x00000005" . $fullRow . "0x00000015" . $fullRow .
        "0x00000025" . $zeroRow),
      '49 bytes' => array(array(str_repeat('A', 48) . '0'),
        "0x00000005" . $fullRow . "0x00000015" . $fullRow .
        "0x00000025" . $fullRow . "0x00000035" . $zeroRow),
      'split before row boundary' => array(array(str_repeat('A', 15), 'A0'),
        $twoRows),
      'zero in separate chunk' => array(array(str_repeat('A', 16), '0'),
        $twoRows)
    );
  }

  public function testTrailingZeroWithHighlightAcrossRows()
  {
    $state = new HighlightState(new HighlightRenderer());
    $highlight = new Highlight(self::START_OFFSET, self::START_OFFSET + 32,
      Highlight::MATCH, 0, 0, 'ref1');
    $state->push(new SplitPosition(1, SplitPosition::START, $highlight));
    $result = new PagedHexResult(self::START_OFFSET, $state);
    $state->openExistingElements($result);
    $result->appendContentText(str_repeat('A', 16) . '0');
    $result->appendContentText(str_repeat('B', 15));

    $span = '<span class="hi-match" title="ref1">';
    $this->assertSame(
      "0x00000005 |" . $span .
      "41 41 41 41 41 41 41 41 41 41 41 41 41 41 41 41</span>| |" .
      $span . "AAAAAAAAAAAAAAAA</span>|<br/>\n" .
      "0x00000015 |" . $span .
      "30 42 42 42 42 42 42 42 42 42 42 42 42 42 42 42</span>| |" .
      $span . "0BBBBBBBBBBBBBBB</span>|<br/>\n",
      $result->getText());
  }

  /**
   * A highlight boundary right after the "0" ends the chunk with exactly "0"
   * left over once the first row is full.
   * @dataProvider zeroAtHighlightBoundaryProvider
   */
  public function testZeroAtHighlightBoundary($action, $highlightStart,
    $highlightEnd, $expectedText)
  {
    $state = new HighlightState(new HighlightRenderer());
    $result = new PagedHexResult(self::START_OFFSET, $state);
    $highlight = new Highlight(self::START_OFFSET + $highlightStart,
      self::START_OFFSET + $highlightEnd, Highlight::MATCH, 0, 0, 'ref1');
    if ($action === SplitPosition::END) {
      $state->insertElements(
        array(new SplitPosition(1, SplitPosition::START, $highlight)), $result);
    }
    $result->appendContentText(str_repeat('A', 16) . '0');
    $state->insertElements(array(new SplitPosition(1, $action, $highlight)),
      $result);
    $result->appendContentText(str_repeat('B', 15));
    $state->closeOpenElements($result);

    $this->assertSame($expectedText, $result->getText());
  }

  public static function zeroAtHighlightBoundaryProvider()
  {
    $anchor = '<a id="highlight"></a>';
    $span = '<span class="hi-match" title="ref1">';
    $hexA = "41 41 41 41 41 41 41 41 41 41 41 41 41 41 41 41";
    $hexB = "42 42 42 42 42 42 42 42 42 42 42 42 42 42 42";

    return array(
      'highlight ends after zero' => array(SplitPosition::END, 0, 17,
        "0x00000005 |" . $anchor . $span . $hexA . "</span>| |" .
        $anchor . $span . str_repeat('A', 16) . "</span>|<br/>\n" .
        "0x00000015 |" . $span . "30 </span>" . $hexB . "| |" .
        $span . "0</span>" . str_repeat('B', 15) . "|<br/>\n"),
      'highlight starts after zero' => array(SplitPosition::START, 17, 32,
        "0x00000005 |" . $hexA . "| |" . str_repeat('A', 16) . "|<br/>\n" .
        "0x00000015 |30 " . $anchor . $span . $hexB . "</span>| |0" .
        $anchor . $span . str_repeat('B', 15) . "</span>|<br/>\n")
    );
  }
}
