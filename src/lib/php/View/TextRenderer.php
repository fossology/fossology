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


use Fossology\Lib\Data\SplitPosition;
use Fossology\Lib\Data\TextFragment;

if (!defined('ENT_SUBSTITUTE'))
{
  define('ENT_SUBSTITUTE', 0);
}

class TextRenderer
{
  /**
   * @var HighlightRenderer
   */
  private $highlightRenderer;

  public function __construct(HighlightRenderer $highlightRenderer)
  {
    $this->highlightRenderer = $highlightRenderer;
  }

  /**
   * @param TextFragment $fragment
   * @param SplitPosition[]|null $splitPositions
   * @param bool $insertBacklink
   * @return string
   */
  public function renderText(TextFragment $fragment, $splitPositions = null, $insertBacklink = false)
  {
    $state = new HighlightState($this->highlightRenderer, $insertBacklink);
    $result = $this->render($fragment, $state, new PagedTextResult($fragment->getStartOffset()), $splitPositions);
    return $result->getText();
  }

  /**
   * @param TextFragment $fragment
   * @param SplitPosition[]|null $splitPositions
   * @return string
   */
  public function renderHex(TextFragment $fragment, $splitPositions = null)
  {
    $state = new HighlightState($this->highlightRenderer);
    $result = $this->render($fragment, $state, new PagedHexResult($fragment->getStartOffset(), $state), $splitPositions);
    return $result->getText();
  }

  /**
   * @param TextFragment $fragment
   * @param HighlightState $state
   * @param PagedResult $result
   * @param SplitPosition[]|null $splitPositions
   * @return PagedTextResult
   */
  public function render(TextFragment $fragment, HighlightState $state,
                         PagedResult $result, $splitPositions = null)
  {
    $splitPositions = $splitPositions ? : array();

    foreach ($splitPositions as $actionPosition => $entries)
    {
      /**
       * @var SplitPosition[] $entries
       */
      $isBeforeVisibleRange = $actionPosition < $fragment->getStartOffset();
      $isAfterVisibleRange = $actionPosition >= $fragment->getEndOffset();
      if ($isBeforeVisibleRange || $isAfterVisibleRange)
      {
        $this->processEntriesOutsideVisibleRange($fragment, $state, $result, $entries, $isAfterVisibleRange);
      } else
      {
        $this->processEntriesWithinVisibleRange($fragment, $state, $result, $actionPosition, $entries);
      }
    }
    $this->finalizeContentText($fragment, $state, $result);

    return $result;
  }

  /**
   * @param TextFragment $fragment
   * @param HighlightState $state
   * @param PagedResult $result
   * @param SplitPosition[] $entries
   * @param boolean $isAfterVisibleRange
   */
  protected function processEntriesOutsideVisibleRange(TextFragment $fragment, HighlightState $state, PagedResult $result, $entries, $isAfterVisibleRange)
  {
    if ($isAfterVisibleRange)
    {
      $this->finalizeContentText($fragment, $state, $result);
    }
    $state->processSplitEntries($entries, $result);
  }

  /**
   * @param TextFragment $fragment
   * @param HighlightState $state
   * @param PagedResult $result
   * @param int $actionPosition
   * @param SplitPosition[] $entries
   */
  protected function processEntriesWithinVisibleRange(TextFragment $fragment, HighlightState $state, PagedResult $result, $actionPosition, $entries)
  {
    if ($result->isEmpty())
    {
      $state->openExistingElements($result);
    }
    $result->appendContentText($fragment->getSlice($result->getCurrentOffset(), $actionPosition));
    $state->insertElements($entries, $result);
    assert($result->getCurrentOffset() == $actionPosition);
  }

  /**
   * @param TextFragment $fragment
   * @param HighlightState $state
   * @param PagedResult $result
   */
  protected function finalizeContentText(TextFragment $fragment, HighlightState $state, PagedResult $result)
  {
    if ($result->getCurrentOffset() < $fragment->getEndOffset())
    {
      if ($result->isEmpty())
      {
        $state->openExistingElements($result);
      }
      $result->appendContentText($fragment->getSlice($result->getCurrentOffset()));
      $state->closeOpenElements($result);
    }
  }

}