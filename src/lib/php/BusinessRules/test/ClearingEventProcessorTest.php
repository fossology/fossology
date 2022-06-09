<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\Clearing\ClearingLicense;
use Fossology\Lib\Data\LicenseRef;
use Mockery as M;

class ClearingEventProcessorTest extends \PHPUnit\Framework\TestCase
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

  protected function setUp() : void
  {
    $this->timestamp = time();
    $this->clearingEventProcessor = new ClearingEventProcessor();

    $this->addedLicense = M::mock(ClearingLicense::class);
    $this->addedLicense->shouldReceive("getShortName")->withNoArgs()->andReturn($this->addedName);
    $this->addedLicense->shouldReceive("getId")->withNoArgs()->andReturn($this->addedId);

    $this->addedEvent = M::mock(ClearingEvent::class);
    $this->addedEvent->shouldReceive("getLicenseShortName")->withNoArgs()->andReturn($this->addedName);
    $this->addedEvent->shouldReceive("getLicenseId")->withNoArgs()->andReturn($this->addedId);
    $this->addedEvent->shouldReceive("getClearingLicense")->withNoArgs()->andReturn($this->addedLicense);
    $this->addedEvent->shouldReceive("getTimeStamp")->withNoArgs()->andReturn($this->timestamp);
    $this->addedEvent->shouldReceive("isRemoved")->withNoArgs()->andReturn(false);

    $this->removedLicense = M::mock(ClearingLicense::class);
    $this->removedLicense->shouldReceive("getShortName")->withNoArgs()->andReturn($this->removedName);
    $this->removedLicense->shouldReceive("getId")->withNoArgs()->andReturn($this->removedId);
    $this->removedEvent = M::mock(ClearingEvent::class);
    $this->removedEvent->shouldReceive("getLicenseShortName")->withNoArgs()->andReturn($this->removedName);
    $this->removedEvent->shouldReceive("getLicenseId")->withNoArgs()->andReturn($this->removedId);
    $this->removedEvent->shouldReceive("getClearingLicense")->withNoArgs()->andReturn($this->removedLicense);
    $this->removedEvent->shouldReceive("getTimeStamp")->withNoArgs()->andReturn($this->timestamp);
    $this->removedEvent->shouldReceive("isRemoved")->withNoArgs()->andReturn(true);
  }

  protected function tearDown() : void
  {
    M::close();
  }

  /**
   * @return array
   */
  protected function createEvents()
  {
    $license = M::mock(ClearingLicense::class);

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

    $license1 = M::mock(ClearingLicense::class);
    $license1->shouldReceive("getLicenseId")->withNoArgs()->andReturn($licAId);
    $events[] = $this->createEvent($this->timestamp, $license1, false);

    $license2 = M::mock(ClearingLicense::class);
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
    $licenseRef1 = M::mock(ClearingLicense::class);
    $licenseRef1->shouldReceive("getLicenseId")->withNoArgs()->andReturn($licId);
    $events[] = $this->createEvent($this->timestamp, $licenseRef1, false);

    $licenseRef2 = M::mock(ClearingLicense::class);
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

    $licenseRef1 = M::mock(ClearingLicense::class);
    $licenseRef1->shouldReceive("getLicenseId")->withNoArgs()->andReturn($licId);
    $events[] = $this->createEvent($this->timestamp, $licenseRef1);

    $licenseRef2 = M::mock(ClearingLicense::class);
    $licenseRef2->shouldReceive("getLicenseId")->withNoArgs()->andReturn($licId);
    $events[] = $this->createEvent($this->timestamp+60, $licenseRef2);

    $filteredEvents = $this->clearingEventProcessor->filterEffectiveEvents($events);

    assertThat($filteredEvents, is(arrayWithSize(1)));
    assertThat($filteredEvents, hasKeyValuePair($licId,$events[1]));
  }

  public function testFilterEffectiveEventsOppositeIdenticalEventsOverwriteInOtherOrder()
  {
    $events = array();

    $licenseRef1 = M::mock(ClearingLicense::class);
    $licenseRef1->shouldReceive("getLicenseId")->withNoArgs()->andReturn("fortyTwo");
    $events[] = $this->createEvent($this->timestamp, $licenseRef1);

    $licenseRef2 = M::mock(ClearingLicense::class);
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
