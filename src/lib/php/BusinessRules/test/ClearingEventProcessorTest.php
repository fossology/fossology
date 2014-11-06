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

namespace Fossology\Lib\BusinessRules;

use DateInterval;
use DateTime;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\LicenseRef;
use Mockery as M;


class ClearingEventProcessorTest extends \PHPUnit_Framework_TestCase
{
  private $itemId = 12;
  private $userId = 5;
  private $groupId = 2;
  private $eventType = ClearingEventTypes::USER;

  /** @var ClearingEvent|M\MockInterface */
  private $addedEvent;

  /** @var ClearingEvent|M\MockInterface */
  private $removedEvent;

  private $addedName = "<added>";

  private $removedName = "<removed>";

  /** @var ClearingEventProcessor */
  protected $clearingEventProcessor;

  public function setUp()
  {
    $this->clearingEventProcessor = new ClearingEventProcessor();
    $this->addedEvent = M::mock(ClearingEvent::classname());
    $this->addedEvent->shouldReceive("getLicenseShortName")->withNoArgs()->andReturn($this->addedName);
    $this->addedEvent->shouldReceive("isRemoved")->withNoArgs()->andReturn(false);
    $this->removedEvent = M::mock(ClearingEvent::classname());
    $this->removedEvent->shouldReceive("getLicenseShortName")->withNoArgs()->andReturn($this->removedName);
    $this->removedEvent->shouldReceive("isRemoved")->withNoArgs()->andReturn(true);
  }

  public function testGetCurrentClearingsShouldReturnNothingForEmptyArrays()
  {
    assertThat($this->clearingEventProcessor->getCurrentClearings(array()), is(array(array(), array())));
  }

  public function testGetCurrentClearingsWithSingleAddedEvent()
  {
    assertThat($this->clearingEventProcessor->getCurrentClearings(array($this->addedEvent)),
        is(array(array($this->addedName => $this->addedEvent), array())));
  }

  public function testGetCurrentClearingsWithSingleRemovedEvent()
  {
    assertThat($this->clearingEventProcessor->getCurrentClearings(array($this->removedEvent)),
        is(array(array(), array($this->removedName => $this->removedEvent))));
  }

  public function testGetCurrentClearingsWithAddedAndRemovedEvent()
  {
    assertThat($this->clearingEventProcessor->getCurrentClearings(array($this->addedEvent, $this->removedEvent)),
        is(array(array($this->addedName => $this->addedEvent), array($this->removedName => $this->removedEvent))));
  }

  public function testFilterEventsByTimeWhenNoTimeIsSet()
  {
    list($events, $timestamp) = $this->createEvents();

    $filteredEvents = $this->clearingEventProcessor->filterEventsByTime($events, null);
    assertThat($filteredEvents, is(arrayWithSize(2)));
    assertThat($filteredEvents, is($events));
  }

  public function testFilterEventsByTimeWhenLastTimeIsGiven()
  {
    list($events, $timestamp) = $this->createEvents();

    $timestamp->sub(new DateInterval("PT30M"));
    $filteredEvents = $this->clearingEventProcessor->filterEventsByTime($events, $timestamp);
    assertThat($filteredEvents, is(arrayWithSize(1)));
    assertThat($filteredEvents, is(array($events[0])));
  }

  public function testFilterEventsByTimeWhenLastTimeIsGivenAndExactlyTheLastTime()
  {
    list($events, $timestamp) = $this->createEvents();

    $timestamp->sub(new DateInterval("PT1H"));
    assertThat($events[1]->getDateTime(), is($timestamp));

    $filteredEvents = $this->clearingEventProcessor->filterEventsByTime($events, $timestamp);
    assertThat($filteredEvents, is(arrayWithSize(1)));
    assertThat($filteredEvents, is(array($events[0])));
  }

  /**
   * @return array
   */
  protected function createEvents()
  {
    $timestamp = new DateTime();
    $licenseRef = M::mock(LicenseRef::classname());

    $events = array();
    $events[] = $this->createEvent(clone $timestamp, $licenseRef, false);

    $eventInterval = new DateInterval('PT1H');
    $eventTimestamp = clone $timestamp;
    $eventTimestamp->sub($eventInterval);

    $events[] = $this->createEvent($eventTimestamp, $licenseRef, false);

    return array($events, $timestamp);
  }

  /**
   * @param $eventTimestamp
   * @param $licenseRef
   * @param boolean $isRemoving
   * @return ClearingEvent
   */
  protected function createEvent(DateTime $eventTimestamp, LicenseRef $licenseRef, $isRemoving)
  {
    return new ClearingEvent(1, $this->itemId, $eventTimestamp, $this->userId, $this->groupId, $this->eventType, $licenseRef, $isRemoving, "<reportInfo>", "<comment>");
  }

  public function testFilterEffectiveEvents()
  {
    $timestamp = new DateTime();
    $events = array();

    $licenseRef1 = M::mock(LicenseRef::classname());
    $licenseRef1->shouldReceive("getShortName")->withNoArgs()->andReturn("licA");
    $events[] = $this->createEvent(clone $timestamp, $licenseRef1, false);

    $timestamp->add(new DateInterval("PT1M"));
    $licenseRef2 = M::mock(LicenseRef::classname());
    $licenseRef2->shouldReceive("getShortName")->withNoArgs()->andReturn("licB");
    $events[] = $this->createEvent($timestamp, $licenseRef2, false);

    $filteredEvents = $this->clearingEventProcessor->filterEffectiveEvents($events);

    assertThat($filteredEvents, is(arrayWithSize(2)));
    assertThat($events[0], is($filteredEvents[0]));
    assertThat($events[1], is($filteredEvents[1]));
  }

  public function testFilterEffectiveEventsIdenticalEventsOverride()
  {
    $timestamp = new DateTime();
    $events = array();

    $licenseRef1 = M::mock(LicenseRef::classname());
    $licenseRef1->shouldReceive("getShortName")->withNoArgs()->andReturn("licA");
    $events[] = $this->createEvent(clone $timestamp, $licenseRef1, false);

    $timestamp->add(new DateInterval("PT1M"));
    $licenseRef2 = M::mock(LicenseRef::classname());
    $licenseRef2->shouldReceive("getShortName")->withNoArgs()->andReturn("licA");
    $events[] = $this->createEvent($timestamp, $licenseRef2, false);

    $filteredEvents = $this->clearingEventProcessor->filterEffectiveEvents($events);

    assertThat($filteredEvents, is(arrayWithSize(1)));
    assertThat($events[1], is($filteredEvents[0]));
  }

  public function testFilterEffectiveEventsOppositeIdenticalEventsAnnihilate()
  {
    $timestamp = new DateTime();
    $events = array();

    $licenseRef1 = M::mock(LicenseRef::classname());
    $licenseRef1->shouldReceive("getShortName")->withNoArgs()->andReturn("licA");
    $events[] = $this->createEvent(clone $timestamp, $licenseRef1, false);

    $timestamp->add(new DateInterval("PT1M"));
    $licenseRef2 = M::mock(LicenseRef::classname());
    $licenseRef2->shouldReceive("getShortName")->withNoArgs()->andReturn("licA");
    $events[] = $this->createEvent($timestamp, $licenseRef2, true);

    $filteredEvents = $this->clearingEventProcessor->filterEffectiveEvents($events);

    assertThat($filteredEvents, is(arrayWithSize(0)));
  }

  public function testFilterEffectiveEventsOppositeIdenticalEventsAnnihilateInWrongOrder()
  {
    $timestamp = new DateTime();
    $events = array();

    $licenseRef1 = M::mock(LicenseRef::classname());
    $licenseRef1->shouldReceive("getShortName")->withNoArgs()->andReturn("licA");
    $events[] = $this->createEvent(clone $timestamp, $licenseRef1, true);

    $timestamp->add(new DateInterval("PT1M"));
    $licenseRef2 = M::mock(LicenseRef::classname());
    $licenseRef2->shouldReceive("getShortName")->withNoArgs()->andReturn("licA");
    $events[] = $this->createEvent($timestamp, $licenseRef2, false);

    $filteredEvents = $this->clearingEventProcessor->filterEffectiveEvents($events);

    assertThat($filteredEvents, is(arrayWithSize(0)));
  }
}
 