<?php

namespace Fossology\Lib\View;

use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\SplitPosition;
use Fossology\Lib\Data\TextFragment;
use Mockery as M;

class TextRendererTest extends \PHPUnit_Framework_TestCase
{
  const START_OFFSET = 10;
  const FRAGMENT_TEXT = "foo bar baz quux";

  /**
   * private TextFragment|MockInterface
   */
  private $textFragment;

  /**
   * @var TextRenderer
   */
  private $textRenderer;

  function setUp()
  {
    $this->textFragment = new TextFragment(self::START_OFFSET, self::FRAGMENT_TEXT);
    $this->textRenderer = new TextRenderer(new HighlightRenderer());
  }

  function testRenderHighlightedTextWithNoSplitPosition()
  {
    $renderedText = $this->textRenderer->renderText($this->textFragment);
    assertThat($renderedText, is(self::FRAGMENT_TEXT));
  }

  function testRenderHighlightWithinFragment()
  {
    $highlight1 = new Highlight(15, 18, Highlight::MATCH, 0, 0, 'ref1');

    $splitPositions = array(
        15 => array(new SplitPosition(1, SplitPosition::START, $highlight1)),
        18 => array(new SplitPosition(1, SplitPosition::END, $highlight1)));
    $renderedText = $this->textRenderer->renderText($this->textFragment, $splitPositions);

    assertThat($renderedText, is("foo b<a id=\"highlight\"></a><span style=\"background-color:lightgreen;\" title=\"ref1\">ar </span>baz quux"));
  }

  function testRenderHexHighlightWithinFragment()
  {
    $highlight1 = new Highlight(15, 18, Highlight::MATCH, 0, 0, 'ref1');

    $splitPositions = array(
        15 => array(new SplitPosition(1, SplitPosition::START, $highlight1)),
        18 => array(new SplitPosition(1, SplitPosition::END, $highlight1)));
    $renderedText = $this->textRenderer->renderHex($this->textFragment, $splitPositions);

    assertThat($renderedText, is("0x0000000A |66 6f 6f 20 62 <a id=\"highlight\"></a><span style=\"background-color:lightgreen;\" title=\"ref1\">61 72 20 </span>62 61 7a 20 71 75 75 78| |foo&nbsp;b<a id=\"highlight\"></a><span style=\"background-color:lightgreen;\" title=\"ref1\">ar&nbsp;</span>baz&nbsp;quux|<br/>\n"));
  }

  function testRenderHighlightWithBacklinkWithinFragment()
  {
    $highlight1 = new Highlight(15, 18, Highlight::MATCH, 0, 0, 'ref1');

    $splitPositions = array(
        15 => array(new SplitPosition(1, SplitPosition::START, $highlight1)),
        18 => array(new SplitPosition(1, SplitPosition::END, $highlight1)));
    $renderedText = $this->textRenderer->renderText($this->textFragment, $splitPositions, true);

    assertThat($renderedText, is("foo b<a id=\"highlight\" href=\"#top\">&nbsp;&#8593;&nbsp;</a><span style=\"background-color:lightgreen;\" title=\"ref1\">ar </span>baz quux"));
  }

  function testRenderHighlightedOverlapsStartOfFragment()
  {
    $highlight1 = new Highlight(5, 18, Highlight::MATCH, 0, 0, 'ref1');

    $splitPositions = array(
        5 => array(new SplitPosition(1, SplitPosition::START, $highlight1)),
        18 => array(new SplitPosition(1, SplitPosition::END, $highlight1)));
    $renderedText = $this->textRenderer->renderText($this->textFragment, $splitPositions);

    assertThat($renderedText, is("<span style=\"background-color:lightgreen;\" title=\"ref1\">foo bar </span>baz quux"));
  }

  function testRenderHighlightedOverlapsEndOfFragment()
  {
    $highlight1 = new Highlight(15, 28, Highlight::MATCH, 0, 0, 'ref1');

    $splitPositions = array(
        15 => array(new SplitPosition(1, SplitPosition::START, $highlight1)),
        28 => array(new SplitPosition(1, SplitPosition::END, $highlight1)));
    $renderedText = $this->textRenderer->renderText($this->textFragment, $splitPositions);

    assertThat($renderedText, is("foo b<a id=\"highlight\"></a><span style=\"background-color:lightgreen;\" title=\"ref1\">ar baz quux</span>"));
  }

  function testRenderFragmentFullInsideHighlight()
  {
    $highlight1 = new Highlight(5, 50, Highlight::MATCH, 0, 0, 'ref1');

    $splitPositions = array(
        5 => array(new SplitPosition(1, SplitPosition::START, $highlight1)),
        50 => array(new SplitPosition(1, SplitPosition::END, $highlight1)));
    $renderedText = $this->textRenderer->renderText($this->textFragment, $splitPositions);

    assertThat($renderedText, is("<span style=\"background-color:lightgreen;\" title=\"ref1\">foo bar baz quux</span>"));
  }

  function testRenderHighlightedTextWithFourSplitPositions()
  {
    $highlight1 = new Highlight(12, 18, 'type1', 'ref1', 0, 0);
    $highlight2 = new Highlight(14, 18, 'type2', 'ref2', 0, 0);

    $splitPositions = array(
        12 => array(new SplitPosition(1, SplitPosition::START, $highlight1)),
        14 => array(new SplitPosition(2, SplitPosition::START, $highlight2)),
        18 => array(new SplitPosition(2, SplitPosition::END, $highlight2)),
        20 => array(new SplitPosition(1, SplitPosition::END, $highlight1)));
    $renderedText = $this->textRenderer->renderText($this->textFragment, $splitPositions);

    assertThat($renderedText, is("fo<a id=\"highlight\"></a><span style=\"background-color:lightgray;\" title=\"0\">o <span style=\"background-color:lightgray;\" title=\"0\">bar </span>ba</span>z quux"));
  }

}

