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
  /** @var LicenseRef|M::MockInterface */
  private $licenseRef;

  /** @var AgentRef|M::MockInterface */
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


}
 