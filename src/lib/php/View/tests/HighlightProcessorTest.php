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

use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\License;
use Fossology\Lib\Data\SplitPosition;
use Mockery as M;

class HighlightProcessorTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @var License
   */
  private $license1;
  /**
   * @var LicenseDao
   */
  private $licenseDao;
  /**
   * @var HighlightProcessor
   */
  var $highlight;

  function setUp()
  {
    $this->license1 = new License(10, "shortName", "fullName", "licenseFullText", "URL");

    $this->licenseDao = M::mock(LicenseDao::classname())
        ->shouldReceive('getLicenseById')->with($this->license1->getId())
        ->andReturn($this->license1)->getMock();

    $this->highlight = new HighlightProcessor($this->licenseDao);
  }

  function tearDown()
  {
    M::close();
  }

  function testAddReferenceTexts()
  {
    $highlight1 = new Highlight(5, 8, 'type1', 2, 6, $this->license1->getId());
    $highlights = array($highlight1);

    $this->highlight->addReferenceTexts($highlights);

    assertThat($highlight1->getInfoText(), is("10"));
  }

  function testSplitOverlappingHighlightEntriesReordersByStart()
  {
    $highlight1 = new Highlight(5, 8, 'type1', 'ref1', 0, 0);
    $highlight2 = new Highlight(4, 8, 'text2', 'ref2', 0, 0);
    $highlightInfos = array($highlight1, $highlight2);

    $splitOverlap = $this->highlight->calculateSplitPositions($highlightInfos);

    assertThat($splitOverlap, anArray(
        array(
            4 => array(new SplitPosition(0, SplitPosition::START, $highlight2)),
            5 => array(new SplitPosition(1, SplitPosition::START, $highlight1)),
            8 => array(
                new SplitPosition(1, SplitPosition::END, $highlight1),
                new SplitPosition(0, SplitPosition::END, $highlight2)
            )
        )));
  }

  function testSplitHighlightEntriesContainingAnAtomAtStart()
  {
    $highlight1 = new Highlight(5, 8, 'type1', 'ref1', 0, 0);
    $highlight2 = new Highlight(5, 5, 'type1', 'ref2', 0, 0);
    $highlightInfos = array($highlight1, $highlight2);

    $splitOverlap = $this->highlight->calculateSplitPositions($highlightInfos);

    assertThat($splitOverlap, anArray(
        array(
            5 => array(
                new SplitPosition(0, SplitPosition::START, $highlight1),
                new SplitPosition(1, SplitPosition::ATOM, $highlight2)),
            8 => array(new SplitPosition(0, SplitPosition::END, $highlight1))
        )));
  }

  function testSplitHighlightEntriesContainingAnAtomInTheMiddle()
  {
    $highlight1 = new Highlight(5, 8, 'type1', 'ref1', 0, 0);
    $highlight2 = new Highlight(7, 7, 'type2', 'ref2', 0, 0);
    $highlightInfos = array($highlight1, $highlight2);

    $splitOverlap = $this->highlight->calculateSplitPositions($highlightInfos);

    assertThat($splitOverlap, anArray(
        array(
            5 => array(new SplitPosition(0, SplitPosition::START, $highlight1)),
            7 => array(new SplitPosition(1, SplitPosition::ATOM, $highlight2)),
            8 => array(new SplitPosition(0, SplitPosition::END, $highlight1))
        )));
  }

  function testSplitOverlappingHightlightEntriesReordersByLengthIfStartIsIdentical()
  {
    $highlight1 = new Highlight(5, 8, 'type1', 'ref1', 0, 0);
    $highlight2 = new Highlight(5, 9, 'type2', 'ref2', 0, 0);
    $highlightInfos = array($highlight1, $highlight2);

    $splitOverlap = $this->highlight->calculateSplitPositions($highlightInfos);

    assertThat($splitOverlap, anArray(
        array(
            5 => array(new SplitPosition(0, SplitPosition::START, $highlight2),
                new SplitPosition(1, SplitPosition::START, $highlight1)),
            8 => array(new SplitPosition(1, SplitPosition::END, $highlight1)),
            9 => array(new SplitPosition(0, SplitPosition::END, $highlight2))
        )));
  }

  function testSplitOverlappingHightlightEntriesSplitsOverlappingEntries()
  {
    $highlight1 = new Highlight(5, 8, 'type1', 'ref1', 0, 0);
    $highlight2 = new Highlight(7, 11, 'type2', 'ref2', 0, 0);
    $highlightInfos = array($highlight1, $highlight2);

    $splitOverlap = $this->highlight->calculateSplitPositions($highlightInfos);

    assertThat($splitOverlap, anArray(
        array(
            5 => array(new SplitPosition(0, SplitPosition::START, $highlight1)),
            7 => array(new SplitPosition(1, SplitPosition::START, $highlight2)),
            8 => array(
                new SplitPosition(1, SplitPosition::END, $highlight2),
                new SplitPosition(0, SplitPosition::END, $highlight1),
                new SplitPosition(1, SplitPosition::START, $highlight2)),
            11 => array(new SplitPosition(1, SplitPosition::END, $highlight2))
        )));
  }

  function testSplitOverlappingHightlightEntriesKeepsNonOverlappingEntriesInDepth()
  {
    $highlight1 = new Highlight(5, 15, 'type1', 'ref1', 0, 0);
    $highlight2 = new Highlight(7, 13, 'type2', 'ref2', 0, 0);
    $highlight3 = new Highlight(9, 11, 'type3', 'ref3', 0, 0);
    $highlightInfos = array($highlight1, $highlight2, $highlight3);

    $splitOverlap = $this->highlight->calculateSplitPositions($highlightInfos);

    assertThat($splitOverlap, anArray(
        array(
            5 => array(new SplitPosition(0, SplitPosition::START, $highlight1)),
            7 => array(new SplitPosition(1, SplitPosition::START, $highlight2)),
            9 => array(new SplitPosition(2, SplitPosition::START, $highlight3)),
            11 => array(new SplitPosition(2, SplitPosition::END, $highlight3)),
            13 => array(new SplitPosition(1, SplitPosition::END, $highlight2)),
            15 => array(new SplitPosition(0, SplitPosition::END, $highlight1))
        )));
  }

  function testSplitOverlappingHightlightEntriesSplitsOverlappingEntriesInDepth()
  {
    $highlight1 = new Highlight(5, 11, 'type1', 'ref1', 0, 0);
    $highlight2 = new Highlight(7, 13, 'type2', 'ref2', 0, 0);
    $highlight3 = new Highlight(9, 15, 'type3', 'ref3', 0, 0);
    $highlightInfos = array($highlight1, $highlight2, $highlight3);

    $splitOverlap = $this->highlight->calculateSplitPositions($highlightInfos);

    assertThat($splitOverlap, anArray(
        array(
            5 => array(new SplitPosition(0, SplitPosition::START, $highlight1)),
            7 => array(new SplitPosition(1, SplitPosition::START, $highlight2)),
            9 => array(new SplitPosition(2, SplitPosition::START, $highlight3)),
            11 => array(
                new SplitPosition(2, SplitPosition::END, $highlight3),
                new SplitPosition(1, SplitPosition::END, $highlight2),
                new SplitPosition(0, SplitPosition::END, $highlight1),
                new SplitPosition(1, SplitPosition::START, $highlight2),
                new SplitPosition(2, SplitPosition::START, $highlight3),),
            13 => array(
                new SplitPosition(2, SplitPosition::END, $highlight3),
                new SplitPosition(1, SplitPosition::END, $highlight2),
                new SplitPosition(2, SplitPosition::START, $highlight3),),
            15 => array(new SplitPosition(2, SplitPosition::END, $highlight3))
        )));

  }

  function testSplitOverlappingHightlightEntriesSplitsPartialOverlappingEntries()
  {
    $highlight1 = new Highlight(5, 8, 'type1', 'ref1', 0, 0);
    $highlight2 = new Highlight(10, 13, 'type2', 'ref2', 0, 0);
    $highlight3 = new Highlight(7, 11, 'type3', 'ref3', 0, 0);
    $highlightInfos = array($highlight1, $highlight2, $highlight3);

    $splitOverlap = $this->highlight->calculateSplitPositions($highlightInfos);

    assertThat($splitOverlap, anArray(
        array(
            5 => array(new SplitPosition(0, SplitPosition::START, $highlight1)),
            7 => array(new SplitPosition(1, SplitPosition::START, $highlight3)),
            8 => array(
                new SplitPosition(1, SplitPosition::END, $highlight3),
                new SplitPosition(0, SplitPosition::END, $highlight1),
                new SplitPosition(1, SplitPosition::START, $highlight3)),
            10 => array(
                new SplitPosition(1, SplitPosition::END, $highlight3),
                new SplitPosition(0, SplitPosition::START, $highlight2),
                new SplitPosition(1, SplitPosition::START, $highlight3)),
            11 => array(new SplitPosition(1, SplitPosition::END, $highlight3),),
            13 => array(new SplitPosition(0, SplitPosition::END, $highlight2))
        )));
  }

  function testFlattenHighlightWithSeparatedEntries()
  {
    $highlight1 = new Highlight(5, 8, 'type1', 'ref1', 0, 0);
    $highlight2 = new Highlight(10, 13, 'type2', 'ref2', 0, 0);
    $highlights = array($highlight1, $highlight2);

    $this->highlight->flattenHighlights($highlights);

    assertThat($highlights, anArray(
        array(
            new Highlight(5, 8, Highlight::UNDEFINED, 'ref1', 0, 0),
            new Highlight(10, 13, Highlight::UNDEFINED, 'ref2', 0, 0)
        )));
  }

  function testFlattenHighlightWithOverlappingEntries()
  {
    $highlight1 = new Highlight(5, 10, Highlight::MATCH, 'ref1', 0, 0);
    $highlight2 = new Highlight(8, 13, Highlight::ADDED, 'ref2', 0, 0);
    $highlights = array($highlight1, $highlight2);

    $this->highlight->flattenHighlights($highlights);

    assertThat($highlights, anArray(
        array(
            new Highlight(5, 10, Highlight::UNDEFINED, 'ref1', 0, 0),
            new Highlight(10, 13, Highlight::UNDEFINED, 'ref2', 0, 0)
        )));
  }

  function testFlattenHighlightWithFullyOverlappingEntries()
  {
    $highlight1 = new Highlight(5, 10, Highlight::MATCH, 'ref1', 0, 0);
    $highlight2 = new Highlight(7, 9, Highlight::ADDED, 'ref2', 0, 0);
    $highlights = array($highlight1, $highlight2);

    $this->highlight->flattenHighlights($highlights);

    assertThat($highlights, anArray(
        array(
            new Highlight(5, 10, Highlight::UNDEFINED, 'ref1', 0, 0),
        )));
  }

  function testFlattenHighlightWithIgnoredEntries()
  {
    $highlight1 = new Highlight(5, 10, Highlight::MATCH, 'ref1', 0, 0);
    $highlight2 = new Highlight(7, 9, Highlight::KEYWORD, 'ref2', 0, 0);
    $highlights = array($highlight1, $highlight2);

    $this->highlight->flattenHighlights($highlights, array(Highlight::KEYWORD));

    assertThat($highlights, anArray(
        array(
            new Highlight(5, 10, Highlight::UNDEFINED, 'ref1', 0, 0),
            new Highlight(7, 9, Highlight::KEYWORD, 'ref2', 0, 0)
        )));
  }
}
