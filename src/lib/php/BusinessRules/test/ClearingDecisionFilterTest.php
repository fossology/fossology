<?php
/*
Copyright (C) 2014-2015, Siemens AG

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

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Mockery as M;

class ClearingDecisionFilterTest extends \PHPUnit_Framework_TestCase {

  /** @var ClearingDecisionFilter */
  private $clearingDecisionFilter;

  protected function setUp() {
    $this->clearingDecisionFilter = new ClearingDecisionFilter();
  }

  protected function tearDown() {
    M::close();
  }

  public function testFilterCurrentClearingDecisions() {
    $itemId = 543;
    $pfileId = 432;
    $decision1 = M::mock(ClearingDecision::classname());
    $decision1->shouldReceive("getType")->atLeast()->once()->withNoArgs()->andReturn(DecisionTypes::IDENTIFIED);
    $decision1->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::REPO);
    $decision1->shouldReceive("getUploadTreeId")->andReturn($itemId);
    $decision1->shouldReceive("getPfileId")->andReturn($pfileId);
    $decision2 = M::mock(ClearingDecision::classname());
    $decision2->shouldReceive("getType")->atLeast()->once()->withNoArgs()->andReturn(DecisionTypes::IDENTIFIED);
    $decision2->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::ITEM);
    $decision2->shouldReceive("getUploadTreeId")->andReturn($itemId+1);
    $decision2->shouldReceive("getPfileId")->andReturn($pfileId);
    $decisionIrrel = M::mock(ClearingDecision::classname());
    $decisionIrrel->shouldReceive("getType")->atLeast()->once()->withNoArgs()->andReturn(DecisionTypes::IRRELEVANT);

    $filteredClearingDecisions = $this->clearingDecisionFilter->filterCurrentClearingDecisions(array($decision1, $decisionIrrel, $decision2));

    assertThat($this->clearingDecisionFilter->getDecisionOf($filteredClearingDecisions, $itemId, $pfileId), is(sameInstance($decision1)));
    assertThat($this->clearingDecisionFilter->getDecisionOf($filteredClearingDecisions, $itemId+1, $pfileId), is(sameInstance($decision2)));
    assertThat($this->clearingDecisionFilter->getDecisionOf($filteredClearingDecisions, $itemId+2, $pfileId), is(sameInstance($decision1)));
  }


  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage unhandled clearing decision scope '12345'
   */
  public function testCreateClearingResultCreationFailsOfNoEventsWereFound()
  {
    $itemId = 543;
    $pfileId = 432;
    $decision = M::mock(ClearingDecision::classname());
    $decision->shouldReceive("getType")->atLeast()->once()->withNoArgs()->andReturn(DecisionTypes::IDENTIFIED);
    $decision->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(12345);
    $decision->shouldReceive("getUploadTreeId")->andReturn($itemId);
    $decision->shouldReceive("getPfileId")->andReturn($pfileId);

    $this->clearingDecisionFilter->filterCurrentClearingDecisions(array($decision));
  }

  public function testFilterCurrentReusableClearingDecisions() {
    $itemId = 543;
    $itemId2 = 432;
    $decision1 = M::mock(ClearingDecision::classname());
    $decision1->shouldReceive("getUploadTreeId")->andReturn($itemId);
    $decision2 = M::mock(ClearingDecision::classname());
    $decision2->shouldReceive("getUploadTreeId")->andReturn($itemId2);

    $filteredClearingDecisions = $this->clearingDecisionFilter->filterCurrentReusableClearingDecisions(array($decision1, $decision2));
    $expecedArray = array($itemId => $decision1, $itemId2 => $decision2 );

    assertThat($filteredClearingDecisions, containsInAnyOrder($expecedArray));
  }
}
 