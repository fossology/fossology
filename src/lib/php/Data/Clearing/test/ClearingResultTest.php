<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Clearing;

use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Exception;
use Mockery as M;

class ClearingResultTest extends \PHPUnit\Framework\TestCase
{

  /** @var LicenseRef|M\MockInterface */
  private $licenseRef;
  /** @var ClearingEvent|M\MockInterface */
  private $clearingEvent;
  /** @var AgentClearingEvent|M\MockInterface */
  private $agentClearingEvent1;
  /** @var AgentClearingEvent|M\MockInterface */
  private $agentClearingEvent2;
  /** @var ClearingResult */
  private $licenseDecisionResult;


  protected function setUp() : void
  {
    $this->licenseRef = M::mock(LicenseRef::class);
    $this->clearingEvent = M::mock(ClearingEvent::class);

    $this->agentClearingEvent1 = M::mock(AgentClearingEvent::class);
    $this->agentClearingEvent2 = M::mock(AgentClearingEvent::class);

    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }

  public function testHasAgentDecisionEventIsTrue()
  {
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent, array($this->agentClearingEvent1, $this->agentClearingEvent2));
    assertThat($this->licenseDecisionResult->hasAgentDecisionEvent(), is(true));
  }

  public function testHasAgentDecisionEventIsFalse()
  {
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent);
    assertThat($this->licenseDecisionResult->hasAgentDecisionEvent(), is(false));
  }

  public function testHasDecisionEventIsTrue()
  {
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent, array($this->agentClearingEvent1, $this->agentClearingEvent2));
    assertThat($this->licenseDecisionResult->hasClearingEvent(), is(true));
  }

  public function testHasDecisionEventIsFalse()
  {
    $this->licenseDecisionResult = new ClearingResult(null, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->hasClearingEvent(), is(false));
  }

  public function testGetLicenseRefFromClearingEvent()
  {
    $this->clearingEvent->shouldReceive("getLicenseRef")->once()->andReturn($this->licenseRef);
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getLicenseRef(), is($this->licenseRef));
  }

  public function testGetLicenseRefFromAgentEvents()
  {
    $this->agentClearingEvent1->shouldReceive("getLicenseRef")->once()->andReturn($this->licenseRef);
    $this->licenseDecisionResult = new ClearingResult(null, array($this->agentClearingEvent1, $this->agentClearingEvent2));
    assertThat($this->licenseDecisionResult->getLicenseRef(), is($this->licenseRef));
  }

  public function testGetLicenseIdFromClearingEvent()
  {
    $licenseId = 123;
    $this->clearingEvent->shouldReceive("getLicenseId")->once()->andReturn($licenseId);
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getLicenseId(), is($licenseId));
  }


  public function testGetLicenseIdFromAgentEvent()
  {
    $licenseId = 123;
    $this->agentClearingEvent1->shouldReceive("getLicenseId")->once()->andReturn($licenseId);
    $this->licenseDecisionResult = new ClearingResult(null, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getLicenseId(), is($licenseId));
  }

  public function testGetLicenseShortName()
  {
    $licenseShortName = "<shortName>";
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent, array($this->agentClearingEvent1));
    $this->clearingEvent->shouldReceive("getLicenseShortName")->once()->andReturn($licenseShortName);
    assertThat($this->licenseDecisionResult->getLicenseShortName(), is($licenseShortName));
  }

  public function testGetLicenseFullName()
  {
    $licenseFullName = "<fullName>";
    $this->clearingEvent->shouldReceive("getLicenseFullName")->once()->andReturn($licenseFullName);
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getLicenseFullName(), is($licenseFullName));
  }

  public function testGetCommentWithClearingEvent()
  {
    $comment = "<comment>";
    $this->clearingEvent->shouldReceive("getComment")->once()->andReturn($comment);
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getComment(), is($comment));
  }

  public function testGetCommentWithoutClearingEvent()
  {
    $comment = "";
    $this->licenseDecisionResult = new ClearingResult(null, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getComment(), is($comment));
  }

  public function testGetReportInfoWithClearingEvent()
  {
    $reportInfo = "<reportInfo>";
    $this->clearingEvent->shouldReceive("getReportinfo")->once()->andReturn($reportInfo);
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getReportinfo(), is($reportInfo));
  }


  public function testGetAcknowledgementWithClearingEvent()
  {
    $acknowledgement = "<acknowledgement>";
    $this->clearingEvent->shouldReceive("getAcknowledgement")->once()->andReturn($acknowledgement);
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getAcknowledgement(), is($acknowledgement));
  }

  public function testGetReportInfoWithoutClearingEvent()
  {
    $reportInfo = "";
    $this->licenseDecisionResult = new ClearingResult(null, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getReportinfo(), is($reportInfo));
  }

  public function testGetAcknowledgementWithoutClearingEvent()
  {
    $acknowledgement = "";
    $this->licenseDecisionResult = new ClearingResult(null, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getAcknowledgement(), is($acknowledgement));
  }

  public function testIsRemoved()
  {
    $this->clearingEvent->shouldReceive("isRemoved")->once()->andReturn(true);
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->isRemoved(), is(true));
  }

  public function testGetTimeStamp()
  {
    $ts = time();
    $this->clearingEvent->shouldReceive("getTimeStamp")->once()->andReturn($ts);
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getTimeStamp(), is($ts));
  }

  public function testEventTypeWithClearingEvent()
  {
    $eventType = "<eventType>";
    $this->clearingEvent->shouldReceive("getEventType")->once()->andReturn($eventType);
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getEventType(), is($eventType));
  }

  public function testEventTypeWithoutClearingEvent()
  {
    $eventType = "<eventType>";
    $this->agentClearingEvent1->shouldReceive("getEventType")->once()->andReturn($eventType);
    $this->licenseDecisionResult = new ClearingResult(null, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getEventType(), is($eventType));
  }

  public function testGetLicenseIdWithClearingEvent()
  {
    $licenseId = 123;
    $this->clearingEvent->shouldReceive("getLicenseId")->once()->andReturn($licenseId);
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getLicenseId(), is($licenseId));
  }

  public function testGetLicenseIdWithoutClearingEvent()
  {
    $licenseId = 123;
    $this->agentClearingEvent1->shouldReceive("getLicenseId")->once()->andReturn($licenseId);
    $this->licenseDecisionResult = new ClearingResult(null, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getLicenseId(), is($licenseId));
  }

  public function testGetClearingEvent()
  {
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getClearingEvent(), is($this->clearingEvent));
  }

  public function testGetAgentClearingEvents()
  {
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent, array($this->agentClearingEvent1, $this->agentClearingEvent2));
    assertThat($this->licenseDecisionResult->getAgentDecisionEvents(), is(array(
        $this->agentClearingEvent1, $this->agentClearingEvent2)));
  }

  public function testCreateClearingResultCreationFailsOfNoEventsWereFound()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage("cannot create ClearingEvent without any "
    . "event contained");
    new ClearingResult(null);
  }
}
