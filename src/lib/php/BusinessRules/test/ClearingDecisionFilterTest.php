<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Mockery as M;

class ClearingDecisionFilterTest extends \PHPUnit\Framework\TestCase
{

  /** @var ClearingDecisionFilter */
  private $clearingDecisionFilter;

  protected function setUp() : void
  {
    $this->clearingDecisionFilter = new ClearingDecisionFilter();
  }

  protected function tearDown() : void
  {
    M::close();
  }

  public function testFilterCurrentClearingDecisions()
  {
    $itemId = 543;
    $pfileId = 432;
    $decision1 = M::mock(ClearingDecision::class);
    $decision1->shouldReceive("getType")->atLeast()->once()->withNoArgs()->andReturn(DecisionTypes::IDENTIFIED);
    $decision1->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::REPO);
    $decision1->shouldReceive("getUploadTreeId")->andReturn($itemId);
    $decision1->shouldReceive("getPfileId")->andReturn($pfileId);
    $decision2 = M::mock(ClearingDecision::class);
    $decision2->shouldReceive("getType")->atLeast()->once()->withNoArgs()->andReturn(DecisionTypes::IDENTIFIED);
    $decision2->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::ITEM);
    $decision2->shouldReceive("getUploadTreeId")->andReturn($itemId+1);
    $decision2->shouldReceive("getPfileId")->andReturn($pfileId);
    $decisionIrrel = M::mock(ClearingDecision::class);
    $decisionIrrel->shouldReceive("getType")->atLeast()->once()->withNoArgs()->andReturn(DecisionTypes::IRRELEVANT);

    $filteredClearingDecisions = $this->clearingDecisionFilter->filterCurrentClearingDecisions(array($decision1, $decisionIrrel, $decision2));

    assertThat($this->clearingDecisionFilter->getDecisionOf($filteredClearingDecisions, $itemId, $pfileId), is(sameInstance($decision1)));
    assertThat($this->clearingDecisionFilter->getDecisionOf($filteredClearingDecisions, $itemId+1, $pfileId), is(sameInstance($decision2)));
    assertThat($this->clearingDecisionFilter->getDecisionOf($filteredClearingDecisions, $itemId+2, $pfileId), is(sameInstance($decision1)));
  }


  public function testCreateClearingResultCreationFailsOfNoEventsWereFound()
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("unhandled clearing decision scope '12345'");
    $itemId = 543;
    $pfileId = 432;
    $decision = M::mock(ClearingDecision::class);
    $decision->shouldReceive("getType")->atLeast()->once()->withNoArgs()->andReturn(DecisionTypes::IDENTIFIED);
    $decision->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(12345);
    $decision->shouldReceive("getUploadTreeId")->andReturn($itemId);
    $decision->shouldReceive("getPfileId")->andReturn($pfileId);

    $this->clearingDecisionFilter->filterCurrentClearingDecisions(array($decision));
  }

  public function testFilterCurrentReusableClearingDecisions()
  {
    $itemId = 543;
    $itemId2 = 432;
    $decision1 = M::mock(ClearingDecision::class);
    $decision1->shouldReceive("getUploadTreeId")->andReturn($itemId);
    $decision2 = M::mock(ClearingDecision::class);
    $decision2->shouldReceive("getUploadTreeId")->andReturn($itemId2);

    $filteredClearingDecisions = $this->clearingDecisionFilter->filterCurrentReusableClearingDecisions(array($decision1, $decision2));
    $expecedArray = array($itemId => $decision1, $itemId2 => $decision2 );

    assertThat($filteredClearingDecisions, containsInAnyOrder($expecedArray));
  }
}
