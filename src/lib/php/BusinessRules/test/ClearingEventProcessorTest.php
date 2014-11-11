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
  private $timestamp;

  /** @var LicenseRef|M\MockInterface */
  private $addedLicenseRef;

  /** @var ClearingEvent|M\MockInterface */
  private $addedEvent;

  /** @var LicenseRef|M\MockInterface */
  private $removedLicenseRef;

  /** @var ClearingEvent|M\MockInterface */
  private $removedEvent;

  private $addedName = "<added>";

  private $removedName = "<removed>";

  /** @var ClearingEventProcessor */
  protected $clearingEventProcessor;

  public function setUp()
  {
    $this->timestamp = new DateTime();
    $this->clearingEventProcessor = new ClearingEventProcessor();

    $this->addedLicenseRef = M::mock(LicenseRef::classname());
    $this->addedEvent = M::mock(ClearingEvent::classname());
    $this->addedEvent->shouldReceive("getLicenseShortName")->withNoArgs()->andReturn($this->addedName);
    $this->addedEvent->shouldReceive("getLicenseRef")->withNoArgs()->andReturn($this->addedLicenseRef);
    $this->addedEvent->shouldReceive("getDateTime")->withNoArgs()->andReturn($this->timestamp);
    $this->addedEvent->shouldReceive("isRemoved")->withNoArgs()->andReturn(false);

    $this->removedLicenseRef = M::mock(LicenseRef::classname());
    $this->removedEvent = M::mock(ClearingEvent::classname());
    $this->removedEvent->shouldReceive("getLicenseShortName")->withNoArgs()->andReturn($this->removedName);
    $this->removedEvent->shouldReceive("getLicenseRef")->withNoArgs()->andReturn($this->removedLicenseRef);
    $this->removedEvent->shouldReceive("getDateTime")->withNoArgs()->andReturn($this->timestamp);
    $this->removedEvent->shouldReceive("isRemoved")->withNoArgs()->andReturn(true);
  }

  public function testGetCurrentClearingsShouldReturnNothingForEmptyArrays()
  {
    assertThat($this->clearingEventProcessor->getCurrentClearingState(array()), is(array(array(), array())));
  }

  public function testGetCurrentClearingsWithSingleAddedEvent()
  {
    assertThat($this->clearingEventProcessor->getCurrentClearingState(array($this->addedEvent)),
        is(array(array($this->addedName => $this->addedLicenseRef), array())));
  }

  public function testGetCurrentClearingsWithSingleRemovedEvent()
  {
    assertThat($this->clearingEventProcessor->getCurrentClearingState(array($this->removedEvent)),
        is(array(array(), array($this->removedName => $this->removedLicenseRef))));
  }

  public function testGetCurrentClearingsWithAddedAndRemovedEvent()
  {
    assertThat($this->clearingEventProcessor->getCurrentClearingState(array($this->addedEvent, $this->removedEvent)),
        is(array(array($this->addedName => $this->addedLicenseRef), array($this->removedName => $this->removedLicenseRef))));
  }

  public function testGetFilteredState() {
    $filteredEvent = M::mock(ClearingEvent::classname());
    $filteredEvent->shouldReceive("getLicenseShortName")->withNoArgs()->andReturn($this->removedName);
    $filteredEvent->shouldReceive("isRemoved")->withNoArgs()->andReturn(false);

    $events = array($this->addedEvent, $filteredEvent, $this->removedEvent);
    $licenseState = array(array($this->addedName => $this->addedLicenseRef), array($this->removedName => $this->removedLicenseRef));

    $result = $this->clearingEventProcessor->getFilteredState($events);

    assertThat($result, is($licenseState));
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
    $licenseRef = M::mock(LicenseRef::classname());

    $events = array();
    $events[] = $this->createEvent(clone $this->timestamp, $licenseRef, false);

    $eventInterval = new DateInterval('PT1H');
    $eventTimestamp = clone $this->timestamp;
    $eventTimestamp->sub($eventInterval);

    $events[] = $this->createEvent($eventTimestamp, $licenseRef, false);

    return array($events, $this->timestamp);
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

  public function testFilterEffectiveEventsOppositeIdenticalEventsOverwrite()
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

    assertThat($filteredEvents, is(arrayWithSize(1)));
    assertThat($events[1], is($filteredEvents[0]));
  }

  public function testFilterEffectiveEventsOppositeIdenticalEventsOverwriteInOtherOrder()
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

    assertThat($filteredEvents, is(arrayWithSize(1)));
    assertThat($events[1], is($filteredEvents[0]));
  }

  public function testFilterEffectiveEventsWithEmptyArray()
  {
    assertThat($this->clearingEventProcessor->filterEffectiveEvents(array()), is(emptyArray()));
  }

  public function testGetUnhandledLicenseWithUnhandledLicenses() {
    $timestamp = new DateTime();
    $events = array();

    $licenseRef1 = M::mock(LicenseRef::classname());
    $licenseRef1->shouldReceive("getShortName")->withNoArgs()->andReturn("licA");
    $events[] = $this->createEvent(clone $timestamp, $licenseRef1, true);

    $licenseRef2 = M::mock(LicenseRef::classname());
    $licenseRef2->shouldReceive("getShortName")->withNoArgs()->andReturn("licB");

    $unhandledLicenses = $this->clearingEventProcessor->getUnhandledLicenses($events, array("licB" => $licenseRef2));

    assertThat($unhandledLicenses, is(array("licB" => $licenseRef2)));
  }

  public function testGetUnhandledLicenseWithNoUnhandledLicenses() {
    $timestamp = new DateTime();
    $events = array();

    $licenseRef1 = M::mock(LicenseRef::classname());
    $licenseRef1->shouldReceive("getShortName")->withNoArgs()->andReturn("licA");
    $events[] = $this->createEvent(clone $timestamp, $licenseRef1, true);

    $unhandledLicenses = $this->clearingEventProcessor->getUnhandledLicenses($events, array("licA" => $licenseRef1));

    assertThat($unhandledLicenses, is(emptyArray()));
  }

  public function testIndexByLicenseShortName() {
    $events = array($this->addedEvent, $this->removedEvent);

    $result = $this->clearingEventProcessor->indexByLicenseShortName($events);

    assertThat($result, is(array($this->addedName => $this->addedEvent, $this->removedName => $this->removedEvent)));
  }

  public function testIndexByLicenseShortNameWithEmtpyArray() {
    $result = $this->clearingEventProcessor->indexByLicenseShortName(array());

    assertThat($result, is(emptyArray()));
  }
}
 