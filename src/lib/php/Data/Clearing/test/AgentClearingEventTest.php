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

use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\LicenseRef;
use Mockery as M;

class AgentClearingEventTest extends \PHPUnit_Framework_TestCase {
  /** @var LicenseRef|M\MockInterface */
  private $licenseRef;

  /** @var AgentRef|M\MockInterface */
  private $agentRef;

  /** @var int */
  private $matchId = 1234;

  /** @var int */
  private $percentage = 95;

  /** @var AgentClearingEvent */
  private $agentClearingEvent;

  public function setUp() {
    $this->licenseRef = M::mock(LicenseRef::classname());
    $this->agentRef = M::mock(AgentRef::classname());

    $this->agentClearingEvent = new AgentClearingEvent($this->licenseRef, $this->agentRef, $this->matchId, $this->percentage);
  }

  public function tearDown() {
    M::close();
  }

  public function testGetMatchId() {
    assertThat($this->agentClearingEvent->getMatchId(), is($this->matchId));
  }

  public function testGetLicenseRef() {
    assertThat($this->agentClearingEvent->getLicenseRef(), is($this->licenseRef));
  }

  public function testGetLicenseId() {
    $licenseId = 1234;
    $this->licenseRef->shouldReceive('getId')->once()->withNoArgs()->andReturn($licenseId);

    assertThat($this->agentClearingEvent->getLicenseId(), is($licenseId));
  }

  public function testGetLicenseShortName() {
    $licenseShortname = "<licenseShortname>";
    $this->licenseRef->shouldReceive('getShortName')->once()->withNoArgs()->andReturn($licenseShortname);

    assertThat($this->agentClearingEvent->getLicenseShortName(), is($licenseShortname));
  }

  public function testGetLicenseFullName() {
    $licenseFullName = "<licenseFullName>";
    $this->licenseRef->shouldReceive('getFullName')->once()->withNoArgs()->andReturn($licenseFullName);

    assertThat($this->agentClearingEvent->getLicenseFullName(), is($licenseFullName));
  }

  public function testGetEventType() {
    assertThat($this->agentClearingEvent->getEventType(), is(ClearingResult::AGENT_DECISION_TYPE));
  }

  public function testGetComment() {
    assertThat($this->agentClearingEvent->getComment(), is(""));
  }

  public function testGetReportinfo() {
    assertThat($this->agentClearingEvent->getReportinfo(), is(""));
  }

  public function testIsRemoved() {
    assertThat($this->agentClearingEvent->isRemoved(), is(false));
  }

  public function testGetAgentRef() {
    assertThat($this->agentClearingEvent->getAgentRef(), is($this->agentRef));
  }

  public function testGetAgentId() {
    $agentId = 1234;
    $this->agentRef->shouldReceive('getAgentId')->once()->withNoArgs()->andReturn($agentId);

    assertThat($this->agentClearingEvent->getAgentId(), is($agentId));
  }

  public function testGetAgentName() {
    $agentName = "<agentName>";
    $this->agentRef->shouldReceive('getAgentName')->once()->withNoArgs()->andReturn($agentName);

    assertThat($this->agentClearingEvent->getAgentName(), is($agentName));
  }

  public function testGetPercentage() {
    assertThat($this->agentClearingEvent->getPercentage(), is($this->percentage));
  }
}
 