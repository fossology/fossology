<?php
/*
Copyright (C) 2014, Siemens AG
Authors: Andreas Würl, Steffen Weber

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

use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\License;
use Fossology\Lib\Data\SplitPosition;
use Fossology\Lib\Util\Object;

class HighlightProcessor extends Object
{
  const LEVEL = 'level';
  const ACTION = 'action';
  const ENTRY = 'entry';
  const REF_TEXT_MAX_LENGTH = 100;

  /**
   * @var LicenseDao
   */
  private $licenseDao;

  public function __construct(LicenseDao $licenseDao)
  {
    $this->licenseDao = $licenseDao;
  }

  /**
   * @param Highlight[] $highlights
   */
  public function addReferenceTexts(&$highlights)
  {
    $licenses = array();
    foreach ($highlights as &$highlight)
    {
      if ($highlight->hasLicenseId())
      {
        $licenseId = $highlight->getLicenseId();

        if (!array_key_exists($licenseId, $licenses))
        {
          $licenses[$licenseId] = $this->licenseDao->getLicenseById($licenseId);
        }
        /**
         * @var License $license
         */
        $license = $licenses[$licenseId];
        $licenseReferenceText = ": '" . $this->getReferenceText($license, $highlight) . "'";
        $includeReferenceText = $highlight->getType() != Highlight::MATCH && $highlight->getRefLength() > 0;
        $infoText = $license->getShortName() . ($includeReferenceText ? $licenseReferenceText : "");
        $highlight->setInfoText($infoText);
      }
    }
  }

  /**
   * @param License $license
   * @param Highlight $highlight
   * @return string
   */
  protected function getReferenceText(License $license, Highlight $highlight)
  {
    $referenceText = substr($license->getText(), $highlight->getRefStart(), min($highlight->getRefLength(), self::REF_TEXT_MAX_LENGTH));
    return $referenceText . ($highlight->getRefLength() > self::REF_TEXT_MAX_LENGTH ? " ... " : "");
  }


  /**
   * @param Highlight[] $highlights
   * @return array
   */
  public function calculateSplitPositions($highlights)
  {
    $this->sortHighlights($highlights);

    $splitPositions = $this->getSplitPositions($highlights);

    $this->filterMultipleAtomEntries($splitPositions);

    $this->sortSplitPositionEntries($splitPositions);

    return $splitPositions;
  }

  /**
   * @param Highlight[] $highlights
   * @param string[] $excludedTypes
   */
  public function flattenHighlights(&$highlights, $excludedTypes=array())
  {
    $excludedTypesSet = array();
    foreach ($excludedTypes as $type)
    {
      $excludedTypesSet[$type] = $type;
    }

    $highlights = array_unique($highlights, SORT_REGULAR);
    $this->sortHighlights($highlights);

    $currentPosition = 0;
    /**
     * Highlight[] $highlights
     */
    foreach ($highlights as $key => $highlight)
    {
      $isAllowedType = !array_key_exists($highlight->getType(), $excludedTypesSet);
      if ($isAllowedType)
      {
        if ($highlight->getEnd() < $currentPosition)
        {
          unset($highlights[$key]);
        } else
        {
          $startPosition = max($highlight->getStart(), $currentPosition);
          $highlights[$key] = new Highlight($startPosition, $highlight->getEnd(), "any", $highlight->getRefStart(), $highlight->getRefEnd(), $highlight->getInfoText());
          $currentPosition = $highlight->getEnd();
        }
      }
    }
  }

  /**
   * @param $highlights
   */
  public function sortHighlights(&$highlights)
  {
    if (isset($highlights))
    {
      usort($highlights, array($this->classname(), 'startAndLengthFirstSorter'));
    }
  }

  /**
   * @param $highlightInfos
   * @return mixed
   */
  private function getSplitPositions($highlightInfos)
  {
    $splitPositions = array();
    $level = 0;
    do
    {
      $this->addHighlightingLayer($highlightInfos, $splitPositions, $level++);
    } while (!empty($highlightInfos));

    return $splitPositions;
  }

  /**
   * @param Highlight[] $highlightEntries
   * @param $splitPositions
   * @param $level
   * @return array
   */
  private function addHighlightingLayer(&$highlightEntries, &$splitPositions, $level)
  {
    $currentPosition = 0;
    foreach ($highlightEntries as $key => &$highlightEntry)
    {
      $start = $highlightEntry->getStart();
      $end = $highlightEntry->getEnd();

      if ($start >= $currentPosition)
      {
        $this->addAllSplitPositions($splitPositions, $level, $highlightEntry);

        ksort($splitPositions);
        $currentPosition = $end;

        unset($highlightEntries[$key]);
      }
    }
  }


  /**
   * @param $splitPositions
   * @param $level
   * @param Highlight $highlightEntry
   * @return mixed
   */
  private function addAllSplitPositions(&$splitPositions, $level, $highlightEntry)
  {
    $start = $highlightEntry->getStart();
    $end = $highlightEntry->getEnd();

    $splitStart = $start;
    foreach ($splitPositions as $splitPosition => $dummy)
    {
      if ($start < $splitPosition && $splitPosition < $end)
      {
        $this->addSingleSectionSplitPositions($splitPositions, $splitStart, $splitPosition, $level, $highlightEntry);
        $splitStart = $splitPosition;
      }
    }
    $this->addSingleSectionSplitPositions($splitPositions, $splitStart, $end, $level, $highlightEntry);
  }

  /**
   * @param $splitPositions
   * @param $start
   * @param $end
   * @param $level
   * @param $highlightEntry
   */
  private function addSingleSectionSplitPositions(&$splitPositions, $start, $end, $level, $highlightEntry)
  {
    if ($start == $end)
    {
      $splitPositions[$start][] = new SplitPosition($level, SplitPosition::ATOM, $highlightEntry);
    } else
    {
      $splitPositions[$start][] = new SplitPosition($level, SplitPosition::START, $highlightEntry);
      $splitPositions[$end][] = new SplitPosition($level, SplitPosition::END, $highlightEntry);
    }
  }

  /**
   * /brief user defined auxilary function for sorting
   */
  private function startAndLengthFirstSorter(Highlight $a, Highlight $b)
  {
    if ($a->getStart() < $b->getStart())
      return -1;
    else if ($a->getStart() > $b->getStart())
      return 1;
    else if ($a->getEnd() > $b->getEnd())
      return -1;
    else
      return ($a->getEnd() == $b->getEnd()) ? 0 : 1;
  }

  private function splitPositionEntrySorter(SplitPosition $a, SplitPosition $b)
  {
    $leftAction = $a->getAction();
    $rightAction = $b->getAction();
    $leftAction = $leftAction == SplitPosition::ATOM ? SplitPosition::START : $leftAction;
    $rightAction = $rightAction == SplitPosition::ATOM ? SplitPosition::START : $rightAction;

    if ($leftAction != $rightAction)
    {
      return strcasecmp($leftAction, $rightAction);
    } else
    {
      return ($leftAction == SplitPosition::START ? 1 : -1) * $this->compare($a->getLevel(), $b->getLevel());
    }
  }

  private function compare($a, $b)
  {
    if ($a == $b)
    {
      return 0;
    }
    return ($a < $b) ? -1 : 1;
  }

  /**
   * @param $splitPositions
   */
  private function filterMultipleAtomEntries(&$splitPositions)
  {
    foreach ($splitPositions as &$splitPositionEntries)
    {
      $atomFound = false;

      foreach ($splitPositionEntries as $key => $entry)
      {
        /**
         * @var SplitPosition $entry
         */
        if ($entry->getAction() == SplitPosition::ATOM)
        {
          if ($atomFound)
          {
            unset($splitPositionEntries[$key]);
          } else
          {
            $atomFound = true;
          }
        }
      }
    }
    unset($splitPositionEntries);
  }

  /**
   * @param $splitPositions
   * @return void
   */
  private function sortSplitPositionEntries(&$splitPositions)
  {
    foreach ($splitPositions as &$splitPositionEntries)
    {
      usort($splitPositionEntries, array($this->classname(), 'splitPositionEntrySorter'));
    }
    unset($splitPositionEntries);
  }

}

