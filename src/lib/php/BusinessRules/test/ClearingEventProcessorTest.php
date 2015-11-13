<?php
/*
Copyright (C) 2014-2015, Siemens AG

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

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingLicense;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\LicenseRef;
use Mockery as M;


class ClearingEventProcessorTest extends \PHPUnit_Framework_TestCase
{
  private $itemId = 12;
  private $userId = 5;
  private $groupId = 2;
  private $eventType = ClearingEventTypes::USER;
  private $timestamp;
  /** @var ClearingEvent|M\MockInterface */
  private $addedEvent;
  /** @var ClearingEvent|M\MockInterface */
  private $removedEvent;
  private $addedName = "<added>";
  private $addedId = 400;
  private $removedName = "<removed>";
  private $removedId = 399;

  /** @var ClearingEventProcessor */
  protected $clearingEventProcessor;

  protected function setUp()
  {
    $this->timestamp = time();
    $this->clearingEventProcessor = new ClearingEventProcessor();

    $this->addedLicense = M::mock(ClearingLicense::classname());
    $this->addedLicense->shouldReceive("getShortName")->withNoArgs()->andReturn($this->addedName);
    $this->addedLicense->shouldReceive("getId")->withNoArgs()->andReturn($this->addedId);
    
    $this->addedEvent = M::mock(ClearingEvent::classname());
    $this->addedEvent->shouldReceive("getLicenseShortName")->withNoArgs()->andReturn($this->addedName);
    $this->addedEvent->shouldReceive("getLicenseId")->withNoArgs()->andReturn($this->addedId);
    $this->addedEvent->shouldReceive("getClearingLicense")->withNoArgs()->andReturn($this->addedLicense);
    $this->addedEvent->shouldReceive("getTimeStamp")->withNoArgs()->andReturn($this->timestamp);
    $this->addedEvent->shouldReceive("isRemoved")->withNoArgs()->andReturn(false);

    $this->removedLicense = M::mock(ClearingLicense::classname());
    $this->removedLicense->shouldReceive("getShortName")->withNoArgs()->andReturn($this->removedName);
    $this->removedLicense->shouldReceive("getId")->withNoArgs()->andReturn($this->removedId);
    $this->removedEvent = M::mock(ClearingEvent::classname());
    $this->removedEvent->shouldReceive("getLicenseShortName")->withNoArgs()->andReturn($this->removedName);
    $this->removedEvent->shouldReceive("getLicenseId")->withNoArgs()->andReturn($this->removedId);
    $this->removedEvent->shouldReceive("getClearingLicense")->withNoArgs()->andReturn($this->removedLicense);
    $this->removedEvent->shouldReceive("getTimeStamp")->withNoArgs()->andReturn($this->timestamp);
    $this->removedEvent->shouldReceive("isRemoved")->withNoArgs()->andReturn(true);
  }

  protected function tearDown()
  {
    M::close();
  }

  /**
   * @return array
   */
  protected function createEvents()
  {
    $license = M::mock(ClearingLicense::classname());

    $events = array();
    $events[] = $this->createEvent($this->timestamp, $license);

    $eventTimestamp = $this->timestamp-3600;
    $events[] = $this->createEvent($eventTimestamp, $license);

    return $events;
  }

  /**
   * @param int $eventTimestamp
   * @param ClearingLicense $clearingLicense
   * @return ClearingEvent
   */
  protected function createEvent($eventTimestamp, ClearingLicense $clearingLicense)
  {
    return new ClearingEvent(1, $this->itemId, $eventTimestamp, $this->userId, $this->groupId, $this->eventType, $clearingLicense);
  }

  public function testFilterEffectiveEvents()
  {
    $events = array();
    $licAId = 42;
    $licBId = 23;

    $license1 = M::mock(ClearingLicense::classname());
    $license1->shouldReceive("getLicenseId")->withNoArgs()->andReturn($licAId);
    $events[] = $this->createEvent($this->timestamp, $license1, false);

    $license2 = M::mock(ClearingLicense::classname());
    $license2->shouldReceive("getLicenseId")->withNoArgs()->andReturn($licBId);
    $events[] = $this->createEvent($this->timestamp+60, $license2, false);

    $filteredEvents = $this->clearingEventProcessor->filterEffectiveEvents($events);

    assertThat($filteredEvents, is(arrayWithSize(2)));
    assertThat($filteredEvents, hasKeyValuePair($licAId,$events[0]));
    assertThat($filteredEvents, hasKeyValuePair($licBId,$events[1]));
  }

  public function testFilterEffectiveEventsIdenticalEventsOverride()
  {
    $events = array();
    $licId = 42;
    $licenseRef1 = M::mock(ClearingLicense::classname());
    $licenseRef1->shouldReceive("getLicenseId")->withNoArgs()->andReturn($licId);
    $events[] = $this->createEvent($this->timestamp, $licenseRef1, false);

    $licenseRef2 = M::mock(ClearingLicense::classname());
    $licenseRef2->shouldReceive("getLicenseId")->withNoArgs()->andReturn($licId);
    $events[] = $this->createEvent($this->timestamp+60, $licenseRef2, false);

    $filteredEvents = $this->clearingEventProcessor->filterEffectiveEvents($events);

    assertThat($filteredEvents, is(arrayWithSize(1)));
    assertThat($filteredEvents, hasKeyValuePair($licId,$events[1]));
  }

  public function testFilterEffectiveEventsOppositeIdenticalEventsOverwrite()
  {
    $events = array();
    $licId = 42;

    $licenseRef1 = M::mock(ClearingLicense::classname());
    $licenseRef1->shouldReceive("getLicenseId")->withNoArgs()->andReturn($licId);
    $events[] = $this->createEvent($this->timestamp, $licenseRef1);

    $licenseRef2 = M::mock(ClearingLicense::classname());
    $licenseRef2->shouldReceive("getLicenseId")->withNoArgs()->andReturn($licId);
    $events[] = $this->createEvent($this->timestamp+60, $licenseRef2);

    $filteredEvents = $this->clearingEventProcessor->filterEffectiveEvents($events);

    assertThat($filteredEvents, is(arrayWithSize(1)));
    assertThat($filteredEvents, hasKeyValuePair($licId,$events[1]));
  }

  public function testFilterEffectiveEventsOppositeIdenticalEventsOverwriteInOtherOrder()
  {
    $events = array();

    $licenseRef1 = M::mock(ClearingLicense::classname());
    $licenseRef1->shouldReceive("getLicenseId")->withNoArgs()->andReturn("fortyTwo");
    $events[] = $this->createEvent($this->timestamp, $licenseRef1);

    $licenseRef2 = M::mock(ClearingLicense::classname());
    $licenseRef2->shouldReceive("getLicenseId")->withNoArgs()->andReturn("fortyTwo");
    $events[] = $this->createEvent($this->timestamp+60, $licenseRef2);

    $filteredEvents = $this->clearingEventProcessor->filterEffectiveEvents($events);

    assertThat($filteredEvents, is(arrayWithSize(1)));
    assertThat($filteredEvents, hasKeyValuePair('fortyTwo',$events[1]));
  }

  public function testFilterEffectiveEventsWithEmptyArray()
  {
    assertThat($this->clearingEventProcessor->filterEffectiveEvents(array()), is(emptyArray()));
  }

}
 