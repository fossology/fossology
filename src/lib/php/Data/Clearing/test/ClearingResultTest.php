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

namespace Fossology\Lib\Data\Clearing;

use DateTime;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Exception;
use Mockery as M;

class ClearingResultTest extends \PHPUnit_Framework_TestCase
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
  


  public function setUp()
  {
    $this->licenseRef = M::mock(LicenseRef::classname());
    $this->clearingEvent = M::mock(ClearingEvent::classname());

    $this->agentClearingEvent1 = M::mock(AgentClearingEvent::classname());
    $this->agentClearingEvent2 = M::mock(AgentClearingEvent::classname());
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
  
  public function testGetReportInfoWithoutClearingEvent()
  {
    $reportInfo = "";
    $this->licenseDecisionResult = new ClearingResult(null, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getReportinfo(), is($reportInfo));
  }

  public function testIsRemoved()
  {
    $this->clearingEvent->shouldReceive("isRemoved")->once()->andReturn(true);
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->isRemoved(), is(true));
  }

  public function testGetDateTime()
  {
    $dateTime = new DateTime();
    $this->clearingEvent->shouldReceive("getDateTime")->once()->andReturn($dateTime);
    $this->licenseDecisionResult = new ClearingResult($this->clearingEvent, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->getDateTime(), is($dateTime));
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

  /**
   * @expectedException Exception
   * @expectedExceptionMessage cannot create ClearingEvent without any event contained
   */
  public function testCreateClearingResultCreationFailsOfNoEventsWereFound()
  {
    new ClearingResult(null);
  }
}
 