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

namespace Fossology\Lib\View;

use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\SplitPosition;
use Fossology\Lib\Html\HtmlElement;
use Fossology\Lib\Html\SimpleHtmlElement;
use Mockery as M;
use Mockery\MockInterface;

class HighlightRendererTest extends \PHPUnit_Framework_TestCase
{
  /** @var int */
  private $level = 5;

  /**
   * @var Highlight|MockInterface
   */
  private $highlight;

  /**
   * @var SplitPosition|MockInterface
   */
  private $splitPosition;

  /**
   * @var HtmlElement
   */
  private $htmlElement;

  /**
   * @var HighlightRenderer
   */
  private $highlightRenderer;

  /**
   * @var array
   */
  private $colorMap;

  function setUp()
  {
    $this->highlightRenderer = new HighlightRenderer();
    $this->colorMap = array('type1' => 'red', 'type2' => 'yellow', 'any' => 'gray');

    $this->prepareMocks();
  }


  public function prepareMocks()
  {
    $this->htmlElement = M::mock(SimpleHtmlElement::classname());
    $this->htmlElement->shouldReceive('getOpeningText')->andReturn('<element>');
    $this->htmlElement->shouldReceive('getClosingText')->andReturn('</element>');

    $this->highlight = M::mock(Highlight::classname());
    $this->highlight->shouldReceive('getType')->andReturn(Highlight::MATCH)->byDefault();
    $this->highlight->shouldReceive('getInfoText')->andReturn("<infoText>")->byDefault();
    $this->highlight->shouldReceive('getHtmlElement')->andReturn(null)->byDefault();

    $this->splitPosition = M::mock(SplitPosition::className());
    $this->splitPosition->shouldReceive('getLevel')->andReturn($this->level)->byDefault();
    $this->splitPosition->shouldReceive('getHighlight')->andReturn($this->highlight)->byDefault();
  }

  public function testCreateSpanStart()
  {
    $result = $this->highlightRenderer->createSpanStart($this->splitPosition);

    assertThat($result, is("<span style=\"background-color:lightgreen;\" title=\"<infoText>\">"));
  }

  public function testCreateSpanStartWrappingHtmlElement()
  {
    $this->highlight->shouldReceive('getHtmlElement')->andReturn($this->htmlElement);

    $result = $this->highlightRenderer->createSpanStart($this->splitPosition);

    assertThat($result, is("<span style=\"background-color:lightgreen;\" title=\"<infoText>\"><element>"));
  }

  public function testCreateSpanStartWithUndefinedType()
  {
    $this->highlight->shouldReceive('getType')->andReturn("<anything>");
    $this->highlight->shouldReceive('getInfoText')->andReturn(null);

    $result = $this->highlightRenderer->createSpanStart($this->splitPosition);

    assertThat($result, is("<span style=\"background-color:lightgray;\" title=\"\">"));
  }

  public function testCreateSpanStartWithPadding()
  {
    $this->splitPosition->shouldReceive('getLevel')->andReturn(-1);

    $result = $this->highlightRenderer->createSpanStart($this->splitPosition);

    assertThat($result, is("<span style=\"padding-top:-2px;padding-bottom:-2px;background-color:lightgreen;\" title=\"<infoText>\">"));
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
