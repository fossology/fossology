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
    $this->addedLicenseRef->shouldReceive("getShortName")->withNoArgs()->andReturn($this->addedName);
    $this->addedEvent = M::mock(ClearingEvent::classname());
    $this->addedEvent->shouldReceive("getLicenseShortName")->withNoArgs()->andReturn($this->addedName);
    $this->addedEvent->shouldReceive("getLicenseRef")->withNoArgs()->andReturn($this->addedLicenseRef);
    $this->addedEvent->shouldReceive("getDateTime")->withNoArgs()->andReturn($this->timestamp);
    $this->addedEvent->shouldReceive("isRemoved")->withNoArgs()->andReturn(false);

    $this->removedLicenseRef = M::mock(LicenseRef::classname());
    $this->removedLicenseRef->shouldReceive("getShortName")->withNoArgs()->andReturn($this->removedName);
    $this->removedEvent = M::mock(ClearingEvent::classname());
    $this->removedEvent->shouldReceive("getLicenseShortName")->withNoArgs()->andReturn($this->removedName);
    $this->removedEvent->shouldReceive("getLicenseRef")->withNoArgs()->andReturn($this->removedLicenseRef);
    $this->removedEvent->shouldReceive("getDateTime")->withNoArgs()->andReturn($this->timestamp);
    $this->removedEvent->shouldReceive("isRemoved")->withNoArgs()->andReturn(true);
  }

  function tearDown()
  {
    M::close();
  }

  public function testGetStateShouldReturnNothingForEmptyArrays()
  {
    assertThat($this->clearingEventProcessor->getState(array()), is(array(array(), array())));
  }

  public function testGetStateWithSingleAddedEvent()
  {
    $license = array($this->addedName => $this->addedLicenseRef);
    assertThat($this->clearingEventProcessor->getState(array($this->addedEvent)),
        is(array($license, $license)));
  }

  public function testGetStateWithSingleRemovedEvent()
  {
    assertThat($this->clearingEventProcessor->getState(array($this->removedEvent)),
        is(array(array(), array($this->removedName => $this->removedLicenseRef))));
  }

  public function testGetStateWithAddedAndRemovedEvent()
  {
    assertThat($this->clearingEventProcessor->getState(array($this->addedEvent, $this->removedEvent)),
        is(array(
            array($this->addedName => $this->addedLicenseRef),
            array(
                $this->addedName => $this->addedLicenseRef,
                $this->removedName => $this->removedLicenseRef))));
  }

  public function testFilterEventsByTimeWhenNoTimeIsSet()
  {
    $events = $this->createEvents();

    $filteredEvents = $this->clearingEventProcessor->selectEventsUntilTime($events, null);
    assertThat($filteredEvents, is(arrayWithSize(2)));
    assertThat($filteredEvents, is($events));
  }

  public function testFilterEventsByTimeWhenLastTimeIsGiven()
  {
    $events = $this->createEvents();

    $this->timestamp->sub(new DateInterval("PT1S"));
    $filteredEvents = $this->clearingEventProcessor->selectEventsUntilTime($events, $this->timestamp);
    assertThat($filteredEvents, is(arrayWithSize(1)));
    assertThat($filteredEvents, is(arrayContaining($events[1])));
  }

  public function testFilterEventsByTimeWhenLastTimeIsGivenAndExactlyTheLastTime()
  {
    $events = $this->createEvents();

    $this->timestamp->sub(new DateInterval("PT1H"));
    assertThat($events[1]->getDateTime(), is($this->timestamp));

    $filteredEvents = $this->clearingEventProcessor->selectEventsUntilTime($events, $this->timestamp);
    assertThat($filteredEvents, is(arrayWithSize(1)));
    assertThat($filteredEvents, is(arrayContaining($events[1])));
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

    return $events;
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
    assertThat($events[0], is($filteredEvents['licA']));
    assertThat($events[1], is($filteredEvents['licB']));
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
    assertThat($events[1], is($filteredEvents['licA']));
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
    assertThat($events[1], is($filteredEvents['licA']));
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
    assertThat($events[1], is($filteredEvents['licA']));
  }

  public function testFilterEffectiveEventsWithEmptyArray()
  {
    assertThat($this->clearingEventProcessor->filterEffectiveEvents(array()), is(emptyArray()));
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


  public function testCheckIfAutomaticDecisionCanBeMade()
  {
    $events = array($this->addedEvent, $this->removedEvent);

    $this->assertTrue($this->clearingEventProcessor->checkIfAutomaticDecisionCanBeMade($events));
  }
}
 