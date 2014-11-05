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

use DateTime;
use Fossology\Lib\Data\LicenseRef;
use Mockery as M;

class LicenseDecisionEventTest extends \PHPUnit_Framework_TestCase
{
  /** @var int */
  private $eventId = 12;

  /** @var int */
  private $pfileId = 65;

  /** @var int */
  private $uploadTreeId = 5;

  /** @var DateTime */
  private $dateTime;

  /** @var int */
  private $userId = 93;

  /** @var int */
  private $groupId = 123;

  /** @var string */
  private $eventType = LicenseDecision::USER_DECISION;

  /** @var string */
  private $reportinfo = "<reportinfo>";

  /** @var string */
  private $comment = "<comment>";

  /** @var LicenseRef|M/MockInterface */
  private $licenseRef;

  /** @var LicenseDecisionEvent */
  private $licenseDecisionEvent;

  public function setUp()
  {
    $this->dateTime = new DateTime();
    $this->licenseRef = M::mock(LicenseRef::classname());

    $this->licenseDecisionEvent = new LicenseDecisionEvent($this->eventId, $this->uploadTreeId, $this->dateTime, $this->userId, $this->groupId, $this->eventType, $this->licenseRef, false, $this->reportinfo, $this->comment);
  }

  public function testGetEventId() {
    assertThat($this->licenseDecisionEvent->getEventId(), is($this->eventId));
  }

  public function testGetEventType() {
    assertThat($this->licenseDecisionEvent->getEventType(), is($this->eventType));
  }

  public function testGetDateTime() {
    assertThat($this->licenseDecisionEvent->getDateTime(), is($this->dateTime));
  }

  public function testGetLicenseRef() {
    assertThat($this->licenseDecisionEvent->getLicenseRef(), is($this->licenseRef));
  }

  public function testGetUploadTreeId() {
    assertThat($this->licenseDecisionEvent->getUploadTreeId(), is($this->uploadTreeId));
  }

  public function testGetLicenseId() {
    $licenseId = 1234;
    $this->licenseRef->shouldReceive('getId')->once()->withNoArgs()->andReturn($licenseId);

    assertThat($this->licenseDecisionEvent->getLicenseId(), is($licenseId));
  }

  public function testGetLicenseShortName() {
    $licenseShortname = "<licenseShortname>";
    $this->licenseRef->shouldReceive('getShortName')->once()->withNoArgs()->andReturn($licenseShortname);

    assertThat($this->licenseDecisionEvent->getLicenseShortName(), is($licenseShortname));
  }

  public function testGetLicenseFullName() {
    $licenseFullName = "<licenseFullName>";
    $this->licenseRef->shouldReceive('getFullName')->once()->withNoArgs()->andReturn($licenseFullName);

    assertThat($this->licenseDecisionEvent->getLicenseFullName(), is($licenseFullName));
  }

  public function testIsRemoved() {
    assertThat($this->licenseDecisionEvent->isRemoved(), is(false));
  }

  public function testGetComment() {
    assertThat($this->licenseDecisionEvent->getComment(), is($this->comment));
  }

  public function testGetReportinfo() {
    assertThat($this->licenseDecisionEvent->getReportinfo(), is($this->reportinfo));
  }

  public function testGetUserId() {
    assertThat($this->licenseDecisionEvent->getUserId(), is($this->userId));
  }

  public function testGetGroupId() {
    assertThat($this->licenseDecisionEvent->getGroupId(), is($this->groupId));
  }
}
 