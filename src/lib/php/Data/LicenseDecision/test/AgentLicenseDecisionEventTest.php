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

namespace Fossology\Lib\Data\LicenseDecision;

use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\LicenseRef;
use Mockery as M;

class AgentLicenseDecisionEventTest extends \PHPUnit_Framework_TestCase {
  /** @var LicenseRef|M\MockInterface */
  private $licenseRef;

  /** @var AgentRef|M\MockInterface */
  private $agentRef;

  /** @var int */
  private $matchId = 1234;

  /** @var int */
  private $percentage = 95;

  /** @var AgentLicenseDecisionEvent */
  private $agentLicenseDecisionEvent;

  public function setUp() {
    $this->licenseRef = M::mock(LicenseRef::classname());
    $this->agentRef = M::mock(AgentRef::classname());

    $this->agentLicenseDecisionEvent = new AgentLicenseDecisionEvent($this->licenseRef, $this->agentRef, $this->matchId, $this->percentage);
  }

  public function tearDown() {
    M::close();
  }

  public function testGetEventId() {
    assertThat($this->agentLicenseDecisionEvent->getEventId(), is($this->matchId));
  }

  public function testGetLicenseRef() {
    assertThat($this->agentLicenseDecisionEvent->getLicenseRef(), is($this->licenseRef));
  }

  public function testGetLicenseId() {
    $licenseId = 1234;
    $this->licenseRef->shouldReceive('getId')->once()->withNoArgs()->andReturn($licenseId);

    assertThat($this->agentLicenseDecisionEvent->getLicenseId(), is($licenseId));
  }

  public function testGetLicenseShortName() {
    $licenseShortname = "<licenseShortname>";
    $this->licenseRef->shouldReceive('getShortName')->once()->withNoArgs()->andReturn($licenseShortname);

    assertThat($this->agentLicenseDecisionEvent->getLicenseShortName(), is($licenseShortname));
  }

  public function testGetLicenseFullName() {
    $licenseFullName = "<licenseFullName>";
    $this->licenseRef->shouldReceive('getFullName')->once()->withNoArgs()->andReturn($licenseFullName);

    assertThat($this->agentLicenseDecisionEvent->getLicenseFullName(), is($licenseFullName));
  }

  public function testGetEventType() {
    assertThat($this->agentLicenseDecisionEvent->getEventType(), is(LicenseDecisionResult::AGENT_DECISION_TYPE));
  }

  public function testGetComment() {
    assertThat($this->agentLicenseDecisionEvent->getComment(), is(""));
  }

  public function testGetReportinfo() {
    assertThat($this->agentLicenseDecisionEvent->getReportinfo(), is(""));
  }

  public function testIsGlobal() {
    assertThat($this->agentLicenseDecisionEvent->isGlobal(), is(true));
  }

  public function testIsRemoved() {
    assertThat($this->agentLicenseDecisionEvent->isRemoved(), is(false));
  }

  public function testGetAgentRef() {
    assertThat($this->agentLicenseDecisionEvent->getAgentRef(), is($this->agentRef));
  }

  public function testGetAgentId() {
    $agentId = 1234;
    $this->agentRef->shouldReceive('getAgentId')->once()->withNoArgs()->andReturn($agentId);

    assertThat($this->agentLicenseDecisionEvent->getAgentId(), is($agentId));
  }

  public function testGetAgentName() {
    $agentName = "<agentName>";
    $this->agentRef->shouldReceive('getAgentName')->once()->withNoArgs()->andReturn($agentName);

    assertThat($this->agentLicenseDecisionEvent->getAgentName(), is($agentName));
  }

  public function testGetPercentage() {
    assertThat($this->agentLicenseDecisionEvent->getPercentage(), is($this->percentage));
  }
}
 