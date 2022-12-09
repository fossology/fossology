<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Clearing;

use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\LicenseRef;
use Mockery as M;

class AgentClearingEventTest extends \PHPUnit\Framework\TestCase
{
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

  protected function setUp() : void
  {
    $this->licenseRef = M::mock(LicenseRef::class);
    $this->agentRef = M::mock(AgentRef::class);

    $this->agentClearingEvent = new AgentClearingEvent($this->licenseRef, $this->agentRef, $this->matchId, $this->percentage);
  }

  protected function tearDown() : void
  {
    M::close();
  }

  public function testGetMatchId()
  {
    assertThat($this->agentClearingEvent->getMatchId(), is($this->matchId));
  }

  public function testGetLicenseRef()
  {
    assertThat($this->agentClearingEvent->getLicenseRef(), is($this->licenseRef));
  }

  public function testGetLicenseId()
  {
    $licenseId = 1234;
    $this->licenseRef->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn($licenseId);

    assertThat($this->agentClearingEvent->getLicenseId(), is($licenseId));
  }

  public function testGetLicenseShortName()
  {
    $licenseShortname = "<licenseShortname>";
    $this->licenseRef->shouldReceive('getShortName')
      ->once()
      ->withNoArgs()
      ->andReturn($licenseShortname);

    assertThat($this->agentClearingEvent->getLicenseShortName(),
      is($licenseShortname));
  }

  public function testGetLicenseFullName()
  {
    $licenseFullName = "<licenseFullName>";
    $this->licenseRef->shouldReceive('getFullName')
      ->once()
      ->withNoArgs()
      ->andReturn($licenseFullName);

    assertThat($this->agentClearingEvent->getLicenseFullName(),
      is($licenseFullName));
  }

  public function testGetEventType()
  {
    assertThat($this->agentClearingEvent->getEventType(),
      is(ClearingResult::AGENT_DECISION_TYPE));
  }

  public function testGetComment()
  {
    assertThat($this->agentClearingEvent->getComment(), is(""));
  }

  public function testGetReportinfo()
  {
    assertThat($this->agentClearingEvent->getReportinfo(), is(""));
  }

  public function testGetAcknowledgement()
  {
    assertThat($this->agentClearingEvent->getAcknowledgement(), is(""));
  }

  public function testIsRemoved()
  {
    assertThat($this->agentClearingEvent->isRemoved(), is(false));
  }

  public function testGetAgentRef()
  {
    assertThat($this->agentClearingEvent->getAgentRef(), is($this->agentRef));
  }

  public function testGetAgentId()
  {
    $agentId = 1234;
    $this->agentRef->shouldReceive('getAgentId')
      ->once()
      ->withNoArgs()
      ->andReturn($agentId);

    assertThat($this->agentClearingEvent->getAgentId(), is($agentId));
  }

  public function testGetAgentName()
  {
    $agentName = "<agentName>";
    $this->agentRef->shouldReceive('getAgentName')
      ->once()
      ->withNoArgs()
      ->andReturn($agentName);

    assertThat($this->agentClearingEvent->getAgentName(), is($agentName));
  }

  public function testGetPercentage()
  {
    assertThat($this->agentClearingEvent->getPercentage(), is($this->percentage));
  }
}
