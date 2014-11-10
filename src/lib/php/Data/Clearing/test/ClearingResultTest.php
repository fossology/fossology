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
  private $licenseDecisionEvent;

  /** @var AgentClearingEvent|M\MockInterface */
  private $agentClearingEvent1;

  /** @var AgentClearingEvent|M\MockInterface */
  private $agentClearingEvent2;

  /** @var ClearingResult */
  private $licenseDecisionResult;

  public function setUp()
  {
    $this->licenseRef = M::mock(LicenseRef::classname());
    $this->licenseDecisionEvent = M::mock(ClearingEvent::classname());

    $this->agentClearingEvent1 = M::mock(AgentClearingEvent::classname());
    $this->agentClearingEvent2 = M::mock(AgentClearingEvent::classname());

    $this->licenseDecisionResult = new ClearingResult($this->licenseDecisionEvent, array($this->agentClearingEvent1, $this->agentClearingEvent2));
  }

  public function testHasAgentDecisionEventIsTrue()
  {
    assertThat($this->licenseDecisionResult->hasAgentDecisionEvent(), is(true));
  }

  public function testHasAgentDecisionEventIsFalse()
  {
    $this->licenseDecisionResult = new ClearingResult($this->licenseDecisionEvent);

    assertThat($this->licenseDecisionResult->hasAgentDecisionEvent(), is(false));
  }

  public function testHasDecisionEventIsTrue()
  {
    assertThat($this->licenseDecisionResult->hasClearingEvent(), is(true));
  }

  public function testHasDecisionEventIsFalse()
  {
    $this->licenseDecisionResult = new ClearingResult(null, array($this->agentClearingEvent1));
    assertThat($this->licenseDecisionResult->hasClearingEvent(), is(false));
  }

  public function testGetLicenseRef()
  {
    $this->licenseDecisionEvent->shouldReceive("getLicenseRef")->once()->andReturn($this->licenseRef);

    assertThat($this->licenseDecisionResult->getLicenseRef(), is($this->licenseRef));
  }

  public function testGetLicenseId()
  {
    $licenseId = 123;
    $this->licenseDecisionEvent->shouldReceive("getLicenseId")->once()->andReturn($licenseId);

    assertThat($this->licenseDecisionResult->getLicenseId(), is($licenseId));
  }

  public function testGetLicenseShortName()
  {
    $licenseShortName = "<shortName>";
    $this->licenseDecisionEvent->shouldReceive("getLicenseShortName")->once()->andReturn($licenseShortName);

    assertThat($this->licenseDecisionResult->getLicenseShortName(), is($licenseShortName));
  }

  public function testGetLicenseFullName()
  {
    $licenseFullName = "<fullName>";
    $this->licenseDecisionEvent->shouldReceive("getLicenseFullName")->once()->andReturn($licenseFullName);

    assertThat($this->licenseDecisionResult->getLicenseFullName(), is($licenseFullName));
  }

  public function testGetComment()
  {
    $comment = "<comment>";
    $this->licenseDecisionEvent->shouldReceive("getComment")->once()->andReturn($comment);

    assertThat($this->licenseDecisionResult->getComment(), is($comment));
  }

  public function testGetReportInfo()
  {
    $reportInfo = "<reportInfo>";
    $this->licenseDecisionEvent->shouldReceive("getReportinfo")->once()->andReturn($reportInfo);

    assertThat($this->licenseDecisionResult->getReportinfo(), is($reportInfo));
  }

  public function testIsGlobal()
  {
    $this->licenseDecisionEvent->shouldReceive("isGlobal")->once()->andReturn(true);

    assertThat($this->licenseDecisionResult->isGlobal(), is(true));
  }

  public function testIsRemoved()
  {
    $this->licenseDecisionEvent->shouldReceive("isRemoved")->once()->andReturn(true);

    assertThat($this->licenseDecisionResult->isRemoved(), is(true));
  }

  public function testGetDateTime()
  {
    $dateTime = new DateTime();
    $this->licenseDecisionEvent->shouldReceive("getDateTime")->once()->andReturn($dateTime);

    assertThat($this->licenseDecisionResult->getDateTime(), is($dateTime));
  }

  public function testEventId()
  {
    $eventId = 123423;
    $this->licenseDecisionEvent->shouldReceive("getEventId")->once()->andReturn($eventId);

    assertThat($this->licenseDecisionResult->getEventId(), is($eventId));
  }

  public function testEventType()
  {
    $eventType = "<eventType>";
    $this->licenseDecisionEvent->shouldReceive("getEventType")->once()->andReturn($eventType);

    assertThat($this->licenseDecisionResult->getEventType(), is($eventType));
  }

  public function testGetLicenseIdFromAgentClearingEvent()
  {
    $this->licenseDecisionResult = new ClearingResult(null, array($this->agentClearingEvent1));
    $licenseId = 123;
    $this->agentClearingEvent1->shouldReceive("getLicenseId")->once()->andReturn($licenseId);

    assertThat($this->licenseDecisionResult->getLicenseId(), is($licenseId));
  }

  public function testGetClearingEvent()
  {
    assertThat($this->licenseDecisionResult->getClearingEvent(), is($this->licenseDecisionEvent));
  }

  public function testGetAgentClearingEvents()
  {
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
 