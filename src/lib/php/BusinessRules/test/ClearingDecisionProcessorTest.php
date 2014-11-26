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
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingLicense;
use Fossology\Lib\Data\Clearing\ClearingResult;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Mockery as M;

function ReportCachePurgeAll()
{

}

class ClearingDecisionProcessorTest extends \PHPUnit_Framework_TestCase
{
  const MATCH_ID = 231;
  /** @var int */
  private $uploadTreeId;

  /** @var int */
  private $userId;

  /** @var int */
  private $groupId;

  /** @var AgentLicenseEventProcessor|M\MockInterface */
  private $agentLicenseEventProcessor;

  /** @var AgentDao|M\MockInterface */
  private $agentsDao;

  /** @var ClearingDao|M\MockInterface */
  private $clearingDao;

  /** @var ItemTreeBounds|M\MockInterface */
  private $itemTreeBounds;

  /** @var ClearingDecisionProcessor */
  private $clearingDecisionProcessor;

  public function setUp()
  {
    $this->uploadTreeId = 432;
    $this->userId = 12;
    $this->groupId = 5;

    $this->clearingDao = M::mock(ClearingDao::classname());
    $this->agentLicenseEventProcessor = M::mock(AgentLicenseEventProcessor::classname());
    $this->clearingDecisionProcessor = new ClearingEventProcessor();
    $this->clearingDecisionProcessor;

    $this->itemTreeBounds = M::mock(ItemTreeBounds::classname());
    $this->itemTreeBounds->shouldReceive("getItemId")->withNoArgs()->andReturn($this->uploadTreeId);

    $this->clearingDecisionProcessor = new ClearingDecisionProcessor(
        $this->clearingDao, $this->agentLicenseEventProcessor, $this->clearingDecisionProcessor);
  }

  function tearDown()
  {
    M::close();
  }

  public function testMakeDecisionFromLastEvents()
  {
    $isGlobal = false;
    $addedEvent = $this->createClearingEvent(123, new DateTime(), 13, "licA", "License A");
    $addedLicense = $addedEvent->getClearingLicense();

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array($addedEvent));
    $this->agentLicenseEventProcessor->shouldReceive("getScannerDetectedLicenses")->with($this->itemTreeBounds)->andReturn(array());

    $clearingDecision = M::mock(ClearingDecision::classname());
    $dateTime = new DateTime();
    $dateTime->sub(new \DateInterval("PT1H"));
    $clearingDecision->shouldReceive("getDateAdded")->withNoArgs()->andReturn($dateTime);
    $clearingDecision->shouldReceive("getType")->withNoArgs()->andReturn(DecisionTypes::IDENTIFIED);

    $this->clearingDao->shouldReceive("getRelevantClearingDecision")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn($clearingDecision);

    $this->clearingDao->shouldReceive("insertClearingDecision")->once()
        ->with($this->uploadTreeId, $this->userId, DecisionTypes::IDENTIFIED, $isGlobal, array($addedLicense->getShortName() => $addedLicense), array());
    $this->clearingDao->shouldReceive("removeWipClearingDecision")->once();

    $this->clearingDecisionProcessor->makeDecisionFromLastEvents($this->itemTreeBounds, $this->userId, DecisionTypes::IDENTIFIED, $isGlobal);
  }

  public function testMakeDecisionFromLastEventsWithNoLicenseKnownTypeShouldNotCreateANewDecisionWhenNoLicensesShouldBeRemoved()
  {
    $isGlobal = true;
    $addedEvent = $this->createClearingEvent(123, new DateTime(), 13, "licA", "License A");
    $addedLicenseId = $addedEvent->getLicenseId();

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array($addedEvent));
    $this->agentLicenseEventProcessor->shouldReceive("getScannerDetectedLicenses")->with($this->itemTreeBounds)->andReturn(array());

    $clearingDecision = M::mock(ClearingDecision::classname());
    $dateTime = new DateTime();
    $dateTime->sub(new \DateInterval("PT1H"));
    $clearingDecision->shouldReceive("getDateAdded")->withNoArgs()->andReturn($dateTime);
    $clearingDecision->shouldReceive("getType")->withNoArgs()->andReturn(DecisionTypes::IDENTIFIED);

    $this->clearingDao->shouldReceive("getRelevantClearingDecision")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn($clearingDecision);

    $this->clearingDao->shouldReceive("insertClearingEventFromClearingLicense")->once()->with($this->uploadTreeId, $this->userId, $addedEvent->getClearingLicense(), ClearingEventTypes::USER);

    $this->clearingDao->shouldReceive("insertClearingDecision")->never();
    $this->clearingDao->shouldReceive("removeWipClearingDecision")->never();

    $this->clearingDecisionProcessor->makeDecisionFromLastEvents($this->itemTreeBounds, $this->userId, ClearingDecisionProcessor::NO_LICENSE_KNOWN_DECISION_TYPE, $isGlobal);
  }

  public function testMakeDecisionFromLastEventsWithNoLicenseKnownType()
  {
    $isGlobal = true;
    $eventTime = new DateTime();
    $eventTime->sub(new DateInterval("PT2H"));
    $addedEvent = $this->createClearingEvent(123, $eventTime, 13, "licA", "License A");

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array($addedEvent));
    $this->agentLicenseEventProcessor->shouldReceive("getScannerDetectedLicenses")->with($this->itemTreeBounds)->andReturn(array());

    $clearingDecision = M::mock(ClearingDecision::classname());
    $dateTime = new DateTime();
    $dateTime->sub(new DateInterval("PT1H"));
    $clearingDecision->shouldReceive("getDateAdded")->withNoArgs()->andReturn($dateTime);
    $clearingDecision->shouldReceive("getType")->withNoArgs()->andReturn(DecisionTypes::IDENTIFIED);

    $this->clearingDao->shouldReceive("getRelevantClearingDecision")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn($clearingDecision);


    $this->clearingDao->shouldReceive("insertClearingEventFromClearingLicense")->once()->with($this->uploadTreeId, $this->userId, $addedEvent->getClearingLicense(), ClearingEventTypes::USER);

    $this->clearingDao->shouldReceive("insertClearingDecision")->once();
    $this->clearingDao->shouldReceive("removeWipClearingDecision")->once();

    $this->clearingDecisionProcessor->makeDecisionFromLastEvents($this->itemTreeBounds, $this->userId, ClearingDecisionProcessor::NO_LICENSE_KNOWN_DECISION_TYPE, $isGlobal);
  }

  /**
   * @brief user decides no license, then scanner finds licA, than user removes licA -> no new clearing event should be generated as nothing changes the state
   */
  public function testMakeDecisionFromLastEventsWithDelayedScanner()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef, $agentRef) = $this->createScannerDetectedLicenseDetails();

    $isGlobal = false;
    $removedEvent = $this->createClearingEvent(123, new DateTime(), $licenseRef->getId(), $licenseRef->getShortName(), $licenseRef->getFullName(), ClearingEventTypes::USER, $isRemoved = true);
    $removedLicense = $removedEvent->getLicenseRef();

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array($removedEvent));

    $this->agentLicenseEventProcessor->shouldReceive("getScannerDetectedLicenses")->with($this->itemTreeBounds)->andReturn($scannerResults);

    $clearingDecision = M::mock(ClearingDecision::classname());
    $dateTime = new DateTime();
    $dateTime->sub(new \DateInterval("PT1H"));
    $clearingDecision->shouldReceive("getDateAdded")->withNoArgs()->andReturn($dateTime);
    $clearingDecision->shouldReceive("getType")->withNoArgs()->andReturn(DecisionTypes::IDENTIFIED);

    $this->clearingDao->shouldReceive("getRelevantClearingDecision")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn($clearingDecision);

    $this->clearingDao->shouldReceive("insertClearingDecision")->never();
    $this->clearingDao->shouldReceive("removeWipClearingDecision")->never();

    $this->clearingDecisionProcessor->makeDecisionFromLastEvents($this->itemTreeBounds, $this->userId, DecisionTypes::IDENTIFIED, $isGlobal);
  }

  public function testMakeDecisionFromLastEventsWithInvalidType()
  {
    $this->clearingDao->shouldReceive("getRelevantClearingEvents")->never();
    $this->agentLicenseEventProcessor->shouldReceive("getScannerDetectedLicenses")->never();
    $this->clearingDao->shouldReceive("getRelevantClearingDecision")->never();

    $this->clearingDao->shouldReceive("insertClearingDecision")->never();
    $this->clearingDao->shouldReceive("removeWipClearingDecision")->never();

    $this->clearingDecisionProcessor->makeDecisionFromLastEvents($this->itemTreeBounds, $this->userId, ClearingDecisionProcessor::NO_LICENSE_KNOWN_DECISION_TYPE - 1, false);
  }

  public function testGetCurrentClearingsWithoutDecisions()
  {
    $this->agentLicenseEventProcessor->shouldReceive("getScannerDetectedLicenseDetails")->with($this->itemTreeBounds)->andReturn(array());
    $this->agentLicenseEventProcessor->shouldReceive("getScannedLicenses")->with(array())->andReturn(array());
    $this->clearingDao->shouldReceive("getRelevantClearingEvents")->with($this->userId, $this->uploadTreeId)->andReturn(array());

    list($licenseDecisions, $removedClearings) = $this->clearingDecisionProcessor->getCurrentClearings($this->itemTreeBounds, $this->userId);

    assertThat($licenseDecisions, is(emptyArray()));
    assertThat($removedClearings, is(emptyArray()));
  }

  public function testGetCurrentClearingsWithUserDecisionsOnly()
  {
    $addedEvent = $this->createClearingEvent(123, new DateTime(), 13, "licA", "License A");

    $this->agentLicenseEventProcessor->shouldReceive("getScannerDetectedLicenseDetails")->with($this->itemTreeBounds)->andReturn(array());
    $this->agentLicenseEventProcessor->shouldReceive("getScannedLicenses")->with(array())->andReturn(array());
    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array($addedEvent));

    list($licenseDecisions, $removedClearings) = $this->clearingDecisionProcessor->getCurrentClearings($this->itemTreeBounds, $this->userId);

    assertThat($licenseDecisions, is(arrayWithSize(1)));

    /** @var ClearingResult $result */
    $result = $licenseDecisions[$addedEvent->getLicenseShortName()];
    assertThat($result->getLicenseRef(), is($addedEvent->getLicenseRef()));
    assertThat($result->getClearingEvent(), is($addedEvent));
    assertThat($result->getAgentDecisionEvents(), is(emptyArray()));
    assertThat($removedClearings, is(emptyArray()));
  }

  public function testGetCurrentClearingsWithAgentDecisionsOnly()
  {
    list($scannerResults, $licenseRef, $agentRef) = $this->createScannerDetectedLicenseDetails();

    $this->agentLicenseEventProcessor->shouldReceive("getScannerDetectedLicenseDetails")
        ->with($this->itemTreeBounds)
        ->andReturn($scannerResults);
    $this->agentLicenseEventProcessor->shouldReceive("getScannedLicenses")
        ->with($scannerResults)
        ->andReturn(array("licA" => $licenseRef));
    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array());

    list($licenseDecisions, $removedClearings) = $this->clearingDecisionProcessor->getCurrentClearings($this->itemTreeBounds, $this->userId);

    assertThat($licenseDecisions, is(arrayWithSize(1)));

    /** @var ClearingResult $result */
    $result = $licenseDecisions[$licenseRef->getShortName()];
    assertThat($result->getLicenseRef(), is($licenseRef));
    assertThat($result->getClearingEvent(), is(nullValue()));
    assertThat($result->getAgentDecisionEvents(), is(arrayWithSize(1)));
    assertThat($removedClearings, is(emptyArray()));
  }

  public function testGetCurrentClearingsWithUserAndAgentDecision()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef, $agentRef) = $this->createScannerDetectedLicenseDetails();

    $this->agentLicenseEventProcessor->shouldReceive("getScannerDetectedLicenseDetails")
        ->with($this->itemTreeBounds)
        ->andReturn($scannerResults);
    $this->agentLicenseEventProcessor->shouldReceive("getScannedLicenses")
        ->with($scannerResults)
        ->andReturn(array($licenseRef->getShortName() => $licenseRef));

    $addedEvent = $this->createClearingEvent(123, new DateTime(), $licenseRef->getId(), $licenseRef->getShortName(), $licenseRef->getFullName());
    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array($addedEvent));

    list($licenseDecisions, $removedClearings) = $this->clearingDecisionProcessor->getCurrentClearings($this->itemTreeBounds, $this->userId);

    assertThat($licenseDecisions, is(arrayWithSize(1)));

    /** @var ClearingResult $result */
    $result = $licenseDecisions[$licenseRef->getShortName()];
    assertThat($result->getLicenseRef(), is($licenseRef));
    assertThat($result->getClearingEvent(), is($addedEvent));
    assertThat($result->getAgentDecisionEvents(), is(arrayWithSize(1)));
    assertThat($removedClearings, is(emptyArray()));
  }

  public function testGetCurrentClearingsWithUserRemovedDecisionsOnly()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef, $agentRef) = $this->createScannerDetectedLicenseDetails();
    $removedEvent = $this->createClearingEvent(123, new DateTime(), $licenseRef->getId(), $licenseRef->getShortName(), $licenseRef->getFullName(), ClearingEventTypes::USER, true);

    $this->agentLicenseEventProcessor->shouldReceive("getScannerDetectedLicenseDetails")
        ->with($this->itemTreeBounds)->andReturn($scannerResults);
    $this->agentLicenseEventProcessor->shouldReceive("getScannedLicenses")
        ->with($scannerResults)->andReturn(array("licA" => $licenseRef));
    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array($removedEvent));

    list($licenseDecisions, $removedClearings) = $this->clearingDecisionProcessor->getCurrentClearings($this->itemTreeBounds, $this->userId);

    assertThat($licenseDecisions, is(emptyArray()));
    assertThat($removedClearings, is(arrayWithSize(1)));

    /** @var ClearingResult $result */
    $result = $removedClearings[$removedEvent->getLicenseShortName()];
    assertThat($result->getLicenseRef(), is($removedEvent->getLicenseRef()));
    assertThat($result->getClearingEvent(), is($removedEvent));
    $agentClearingEvents = $result->getAgentDecisionEvents();
    assertThat($agentClearingEvents, is(arrayWithSize(1)));

    $agentEvent = $agentClearingEvents[0];

    assertThat($agentEvent->getAgentRef(), is($agentRef));
    assertThat($agentEvent->getLicenseRef(), is($licenseRef));
    assertThat($agentEvent->getMatchId(), is(self::MATCH_ID));
    assertThat($agentEvent->getPercentage(), is(98));
  }

  public function testGetUnhandledScannerDetectedLicenses()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef) = $this->createScannerDetectedLicenses();

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array());
    $this->agentLicenseEventProcessor->shouldReceive("getScannerDetectedLicenses")
        ->with($this->itemTreeBounds)->andReturn($scannerResults);

    $unhandledScannerDetectedLicenses = $this->clearingDecisionProcessor->getUnhandledScannerDetectedLicenses($this->itemTreeBounds, $this->userId);

    assertThat($unhandledScannerDetectedLicenses, is(array($licenseRef->getShortName() => $licenseRef)));
  }

  public function testGetUnhandledScannerDetectedLicensesWithMatch()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef) = $this->createScannerDetectedLicenses();
    $clearingEvent = $this->createClearingEvent(123, new DateTime(), $licenseRef->getId(), $licenseRef->getShortName(), $licenseRef->getFullName());

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array($clearingEvent));
    $this->agentLicenseEventProcessor->shouldReceive("getScannerDetectedLicenses")
        ->with($this->itemTreeBounds)->andReturn($scannerResults);

    $unhandledScannerDetectedLicenses = $this->clearingDecisionProcessor->getUnhandledScannerDetectedLicenses($this->itemTreeBounds, $this->userId);

    assertThat($unhandledScannerDetectedLicenses, is(emptyArray()));
  }

  public function testGetUnhandledScannerDetectedLicensesWithoutMatch()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef) = $this->createScannerDetectedLicenses();
    $clearingEvent = $this->createClearingEvent(123, new DateTime(), 321, "licB", "License-B");

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array($clearingEvent));
    $this->agentLicenseEventProcessor->shouldReceive("getScannerDetectedLicenses")
        ->with($this->itemTreeBounds)->andReturn($scannerResults);

    $unhandledScannerDetectedLicenses = $this->clearingDecisionProcessor->getUnhandledScannerDetectedLicenses($this->itemTreeBounds, $this->userId);

    assertThat($unhandledScannerDetectedLicenses, is(array($licenseRef->getShortName() => $licenseRef)));
  }

  /**
   * @param $eventId
   * @param $timestamp
   * @param $licenseId
   * @param $licenseShortName
   * @param $licenseFullName
   * @param int $eventType
   * @param bool $isRemoved
   * @param string $reportInfo
   * @param string $comment
   * @return ClearingEvent
   */
  private function createClearingEvent($eventId, $timestamp, $licenseId, $licenseShortName, $licenseFullName, $eventType = ClearingEventTypes::USER, $isRemoved = false, $reportInfo = "<reportInfo>", $comment = "<comment>")
  {
    $licenseRef = new LicenseRef($licenseId, $licenseShortName, $licenseFullName);
    $clearingLicense = new ClearingLicense($licenseRef, $isRemoved, $reportInfo, $comment);
    return new ClearingEvent($eventId, $this->uploadTreeId, $timestamp, $this->userId, $this->groupId, $eventType, $clearingLicense);
  }

  /**
   * @return array
   */
  protected function createScannerDetectedLicenseDetails($agentId = 143, $agentName = "agent", $agentRevision = "1.1", $licenseId = 13, $licenseShortname = "licA", $licenseFullName = "License-A", $matchId = self::MATCH_ID, $percentage = 98)
  {
    $agentRef = new AgentRef($agentId, $agentName, $agentRevision);
    $licenseRef = new LicenseRef($licenseId, $licenseShortname, $licenseFullName);
    $scannerResults = array(
        $licenseShortname => array(
            $agentRef->getAgentName() => array(
                array(
                    "id" => $licenseRef->getId(),
                    "licenseRef" => $licenseRef,
                    "agentRef" => $agentRef,
                    "matchId" => $matchId,
                    "percentage" => $percentage
                )
            )
        )
    );

    return array($scannerResults, $licenseRef, $agentRef);
  }

  /**
   * @return array
   */
  protected function createScannerDetectedLicenses($licenseId = 13, $licenseShortname = "licA", $licenseFullName = "License-A")
  {
    $licenseRef = new LicenseRef($licenseId, $licenseShortname, $licenseFullName);

    $scannerResults = array(
        $licenseRef->getShortName() => $licenseRef
    );

    return array($scannerResults, $licenseRef);
  }

}
