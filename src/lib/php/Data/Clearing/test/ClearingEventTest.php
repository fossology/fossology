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
use Mockery as M;

class ClearingEventTest extends \PHPUnit_Framework_TestCase
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
  private $eventType = ClearingEventTypes::USER;

  /** @var string */
  private $reportinfo = "<reportinfo>";

  /** @var string */
  private $comment = "<comment>";

  /** @var ClearingLicense|M/MockInterface */
  private $clearingLicense;

  /** @var ClearingEvent */
  private $licenseDecisionEvent;

  public function setUp()
  {
    $this->dateTime = new DateTime();
    $this->clearingLicense = M::mock(ClearingLicense::classname());

    $this->licenseDecisionEvent = new ClearingEvent($this->eventId, $this->uploadTreeId, $this->dateTime, $this->userId, $this->groupId, $this->eventType, $this->clearingLicense);
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

  public function testGetClearingLicense() {
    assertThat($this->licenseDecisionEvent->getClearingLicense(), is($this->clearingLicense));
  }

  public function testGetUploadTreeId() {
    assertThat($this->licenseDecisionEvent->getUploadTreeId(), is($this->uploadTreeId));
  }

  public function testGetLicenseId() {
    $licenseId = 1234;
    $this->clearingLicense->shouldReceive('getLicenseId')->once()->withNoArgs()->andReturn($licenseId);

    assertThat($this->licenseDecisionEvent->getLicenseId(), is($licenseId));
  }

  public function testGetLicenseShortName() {
    $licenseShortname = "<licenseShortname>";
    $this->clearingLicense->shouldReceive('getShortName')->once()->withNoArgs()->andReturn($licenseShortname);

    assertThat($this->licenseDecisionEvent->getLicenseShortName(), is($licenseShortname));
  }

  public function testGetLicenseFullName() {
    $licenseFullname = "<licenseFullname>";
    $this->clearingLicense->shouldReceive('getFullName')->once()->withNoArgs()->andReturn($licenseFullname);

    assertThat($this->licenseDecisionEvent->getLicenseFullName(), is($licenseFullname));
  }

  public function testGetUserId() {
    assertThat($this->licenseDecisionEvent->getUserId(), is($this->userId));
  }

  public function testGetGroupId() {
    assertThat($this->licenseDecisionEvent->getGroupId(), is($this->groupId));
  }
}
 