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

  /** @var LicenseRef|M\MockInterface */
  private $addedLicenseRef;

  /** @var ClearingEvent|M\MockInterface */
  private $addedEvent;

  /** @var LicenseRef|M\MockInterface */
  private $removedLicenseRef;

  /** @var ClearingEvent|M\MockInterface */
  private $removedEvent;

  private $addedName = "<added>";
  private $addedId = 400;

  private $removedName = "<removed>";
  private $removedId = 399;

  /** @var ClearingEventProcessor */
  protected $clearingEventProcessor;

  public function setUp()
  {
    $this->timestamp = new DateTime();
    $this->clearingEventProcessor = new ClearingEventProcessor();

    $this->addedLicense = M::mock(ClearingLicense::classname());
    $this->addedLicense->shouldReceive("getShortName")->withNoArgs()->andReturn($this->addedName);
    $this->addedLicense->shouldReceive("getId")->withNoArgs()->andReturn($this->addedId);
    
    $this->addedEvent = M::mock(ClearingEvent::classname());
    $this->addedEvent->shouldReceive("getLicenseShortName")->withNoArgs()->andReturn($this->addedName);
    $this->addedEvent->shouldReceive("getLicenseId")->withNoArgs()->andReturn($this->addedId);
    $this->addedEvent->shouldReceive("getClearingLicense")->withNoArgs()->andReturn($this->addedLicense);
    $this->addedEvent->shouldReceive("getDateTime")->withNoArgs()->andReturn($this->timestamp);
    $this->addedEvent->shouldReceive("isRemoved")->withNoArgs()->andReturn(false);

    $this->removedLicense = M::mock(ClearingLicense::classname());
    $this->removedLicense->shouldReceive("getShortName")->withNoArgs()->andReturn($this->removedName);
    $this->removedLicense->shouldReceive("getId")->withNoArgs()->andReturn($this->removedId);
    $this->removedEvent = M::mock(ClearingEvent::classname());
    $this->removedEvent->shouldReceive("getLicenseShortName")->withNoArgs()->andReturn($this->removedName);
    $this->removedEvent->shouldReceive("getLicenseId")->withNoArgs()->andReturn($this->removedId);
    $this->removedEvent->shouldReceive("getClearingLicense")->withNoArgs()->andReturn($this->removedLicense);
    $this->removedEvent->shouldReceive("getDateTime")->withNoArgs()->andReturn($this->timestamp);
    $this->removedEvent->shouldReceive("isRemoved")->withNoArgs()->andReturn(true);
  }

  function tearDown()
  {
    M::close();
  }

  public function testGetStateShouldReturnNothingForEmptyArrays()
  {
    assertThat($this->clearingEventProcessor->getClearingLicenses(array()), is(array()));
  }

  public function testGetStateWithSingleAddedEvent()
  {
    $license = array($this->addedName => $this->addedLicense);
    assertThat($this->clearingEventProcessor->getClearingLicenses(array($this->addedEvent)),
        is($license));
  }

  public function testGetStateWithSingleRemovedEvent()
  {
    assertThat($this->clearingEventProcessor->getClearingLicenses(array($this->removedEvent)),
        is(array($this->removedName => $this->removedLicense)));
  }

  public function testGetStateWithAddedAndRemovedEvent()
  {
    assertThat($this->clearingEventProcessor->getClearingLicenses(array($this->addedEvent, $this->removedEvent)),
        is(array($this->addedName => $this->addedLicense,
                $this->removedName => $this->removedLicense)));
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
    $license = M::mock(ClearingLicense::classname());

    $events = array();
    $events[] = $this->createEvent(clone $this->timestamp, $license);

    $eventInterval = new DateInterval('PT1H');
    $eventTimestamp = clone $this->timestamp;
    $eventTimestamp->sub($eventInterval);

    $events[] = $this->createEvent($eventTimestamp, $license);

    return $events;
  }

  /**
   * @param DateTime $eventTimestamp
   * @param ClearingLicense $clearingLicense
   * @return ClearingEvent
   */
  protected function createEvent(DateTime $eventTimestamp, ClearingLicense $clearingLicense)
  {
    return new ClearingEvent(1, $this->itemId, $eventTimestamp, $this->userId, $this->groupId, $this->eventType, $clearingLicense);
  }

  public function testFilterEffectiveEvents()
  {
    $timestamp = new DateTime();
    $events = array();

    $license1 = M::mock(ClearingLicense::classname());
    $license1->shouldReceive("getShortName")->withNoArgs()->andReturn("licA");
    $events[] = $this->createEvent(clone $timestamp, $license1, false);

    $timestamp->add(new DateInterval("PT1M"));
    $license2 = M::mock(ClearingLicense::classname());
    $license2->shouldReceive("getShortName")->withNoArgs()->andReturn("licB");
    $events[] = $this->createEvent($timestamp, $license2, false);

    $filteredEvents = $this->clearingEventProcessor->filterEffectiveEvents($events);

    assertThat($filteredEvents, is(arrayWithSize(2)));
    assertThat($events[0], is($filteredEvents['licA']));
    assertThat($events[1], is($filteredEvents['licB']));
  }

  public function testFilterEffectiveEventsIdenticalEventsOverride()
  {
    $timestamp = new DateTime();
    $events = array();

    $licenseRef1 = M::mock(ClearingLicense::classname());
    $licenseRef1->shouldReceive("getShortName")->withNoArgs()->andReturn("licA");
    $events[] = $this->createEvent(clone $timestamp, $licenseRef1, false);

    $timestamp->add(new DateInterval("PT1M"));
    $licenseRef2 = M::mock(ClearingLicense::classname());
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

    $licenseRef1 = M::mock(ClearingLicense::classname());
    $licenseRef1->shouldReceive("getShortName")->withNoArgs()->andReturn("licA");
    $events[] = $this->createEvent(clone $timestamp, $licenseRef1);

    $timestamp->add(new DateInterval("PT1M"));
    $licenseRef2 = M::mock(ClearingLicense::classname());
    $licenseRef2->shouldReceive("getShortName")->withNoArgs()->andReturn("licA");
    $events[] = $this->createEvent($timestamp, $licenseRef2);

    $filteredEvents = $this->clearingEventProcessor->filterEffectiveEvents($events);

    assertThat($filteredEvents, is(arrayWithSize(1)));
    assertThat($events[1], is($filteredEvents['licA']));
  }

  public function testFilterEffectiveEventsOppositeIdenticalEventsOverwriteInOtherOrder()
  {
    $timestamp = new DateTime();
    $events = array();

    $licenseRef1 = M::mock(ClearingLicense::classname());
    $licenseRef1->shouldReceive("getShortName")->withNoArgs()->andReturn("licA");
    $events[] = $this->createEvent(clone $timestamp, $licenseRef1);

    $timestamp->add(new DateInterval("PT1M"));
    $licenseRef2 = M::mock(ClearingLicense::classname());
    $licenseRef2->shouldReceive("getShortName")->withNoArgs()->andReturn("licA");
    $events[] = $this->createEvent($timestamp, $licenseRef2);

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
}
 