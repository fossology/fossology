<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\View;


use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\SplitPosition;

class HighlightState
{
  const PLACEHOLDER = " # ";

  /** @var SplitPosition[]  */
  private $elementStack;
  /** @var HighlightRenderer */
  private $highlightRenderer;
  /** @param boolean */
  private $anchorDrawn;
  /** @var boolean */
  private $insertBacklink;

  public function __construct(HighlightRenderer $highlightRenderer, $insertBacklink = false)
  {
    $this->highlightRenderer = $highlightRenderer;
    $this->insertBacklink = $insertBacklink;
    $this->elementStack = array();
  }

  /**
   * @param SplitPosition $splitPosition
   */
  public function push(SplitPosition $splitPosition)
  {
    $this->elementStack[] = $splitPosition;
  }

  /**
   * @return SplitPosition[]
   */
  public function pop()
  {
    return array_pop($this->elementStack);
  }

  /**
   * @return SplitPosition[]
   */
  public function getElementStack()
  {
    return $this->elementStack;
  }

  /**
   * @param SplitPosition[] $entries
   */
  public function processSplitEntries($entries)
  {
    foreach ($entries as $entry) {
      switch ($entry->getAction()) {
        case SplitPosition::START:
          $this->push($entry);
          $this->checkForAnchor($entry);
          break;
        case SplitPosition::END:
          $this->pop();
          break;
      }
    }
  }

  /**
   * @param SplitPosition[] $entries
   * @param PagedResult $result
   */
  public function insertElements($entries, PagedResult $result)
  {
    foreach ($entries as $entry) {
      switch ($entry->getAction()) {
        case SplitPosition::START:
          $this->push($entry);
          $result->appendMetaText($this->startSpan($entry));
          break;
        case SplitPosition::ATOM:
          $result->appendMetaText($this->startSpan($entry));
          $result->appendMetaText(
            self::PLACEHOLDER . $this->highlightRenderer->createSpanEnd($entry));
          break;

        case SplitPosition::END:
          $this->pop();
          $result->appendMetaText(
            $this->highlightRenderer->createSpanEnd($entry));
          break;
      }
    }
  }

  /**
   * @param \Fossology\Lib\View\PagedResult $result
   */
  public function closeOpenElements(PagedResult $result)
  {
    foreach ($this->elementStack as $splitPosition) {
      $result->appendMetaText(
        $this->highlightRenderer->createSpanEnd($splitPosition));
    }
  }

  /**
   * @param $result
   */
  public function openExistingElements(PagedResult $result)
  {
    foreach ($this->elementStack as $entry) {
      $result->appendMetaText($this->highlightRenderer->createSpanStart($entry));
    }
  }

  /**
   * @param SplitPosition $entry
   * @return string
   */
  protected function startSpan(SplitPosition $entry)
  {
    $anchorText = $this->checkForAnchor($entry)
        ? "<a id=\"highlight\"" . ($this->insertBacklink ? " href=\"#top\">&nbsp;&#8593;&nbsp;" : ">") . "</a>"
        : "";

    return $anchorText . $this->highlightRenderer->createSpanStart($entry);
  }

  /**
   * @param SplitPosition $entry
   * @return bool
   */
  protected function checkForAnchor(SplitPosition $entry)
  {
    $shouldShowAnchor = !$this->anchorDrawn && $entry->getHighlight()->getType() != Highlight::KEYWORD;
    if ($shouldShowAnchor) {
      $this->anchorDrawn = true;
    }
    return $shouldShowAnchor;
  }
}
