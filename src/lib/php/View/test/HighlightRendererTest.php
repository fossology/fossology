<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\View;

use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\SplitPosition;
use Fossology\Lib\Html\HtmlElement;
use Fossology\Lib\Html\SimpleHtmlElement;
use Mockery\MockInterface;
use Mockery as M;

class HighlightRendererTest extends \PHPUnit\Framework\TestCase
{
  /** @var int */
  private $level = 5;
  /** @var Highlight|MockInterface */
  private $highlight;
  /** @var SplitPosition|MockInterface */
  private $splitPosition;
  /** @var HtmlElement */
  private $htmlElement;
  /** @var HighlightRenderer */
  private $highlightRenderer;
  /** @var array */
  private $colorMap;

  function setUp() : void
  {
    $this->highlightRenderer = new HighlightRenderer();
    $this->colorMap = array('type1' => 'red', 'type2' => 'yellow', 'any' => 'gray');

    $this->prepareMocks();
  }

  function tearDown() : void
  {
    M::close();
  }

  public function prepareMocks()
  {
    $this->htmlElement = M::mock(SimpleHtmlElement::class);
    $this->htmlElement->shouldReceive('getOpeningText')->andReturn('<element>');
    $this->htmlElement->shouldReceive('getClosingText')->andReturn('</element>');

    $this->highlight = M::mock(Highlight::class);
    $this->highlight->shouldReceive('getType')->andReturn(Highlight::MATCH)->byDefault();
    $this->highlight->shouldReceive('getInfoText')->andReturn("<infoText>")->byDefault();
    $this->highlight->shouldReceive('getHtmlElement')->andReturn(null)->byDefault();

    $this->splitPosition = M::mock(SplitPosition::class);
    $this->splitPosition->shouldReceive('getLevel')->andReturn($this->level)->byDefault();
    $this->splitPosition->shouldReceive('getHighlight')->andReturn($this->highlight)->byDefault();
  }

  public function testCreateSpanStart()
  {
    $result = $this->highlightRenderer->createSpanStart($this->splitPosition);

    assertThat($result, is("<span class=\"hi-match\" title=\"<infoText>\">"));
  }

  public function testCreateSpanStartWrappingHtmlElement()
  {
    $this->highlight->shouldReceive('getHtmlElement')->andReturn($this->htmlElement);

    $result = $this->highlightRenderer->createSpanStart($this->splitPosition);

    assertThat($result, is("<span class=\"hi-match\" title=\"<infoText>\"><element>"));
  }

  public function testCreateSpanStartWithUndefinedType()
  {
    $this->highlight->shouldReceive('getType')->andReturn("<anything>");
    $this->highlight->shouldReceive('getInfoText')->andReturn(null);

    $result = $this->highlightRenderer->createSpanStart($this->splitPosition);

    assertThat($result, is("<span class=\"hi-undefined\" title=\"\">"));
  }

  public function testCreateSpanStartWithPadding()
  {
    $this->splitPosition->shouldReceive('getLevel')->andReturn(-1);

    $result = $this->highlightRenderer->createSpanStart($this->splitPosition);

    assertThat($result, is("<span class=\"hi-match\" title=\"<infoText>\">"));
  }

  public function testCreateSpanEnd()
  {
    $result = $this->highlightRenderer->createSpanEnd($this->splitPosition);

    assertThat($result, is("</span>"));
  }

  public function testCreateSpanEndWithWrappeingHtmlElement()
  {
    $this->highlight->shouldReceive('getHtmlElement')->andReturn($this->htmlElement);

    $result = $this->highlightRenderer->createSpanEnd($this->splitPosition);

    assertThat($result, is("</element></span>"));
  }
}
