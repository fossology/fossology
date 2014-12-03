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
use Fossology\Lib\Data\Clearing\AgentClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\Clearing\ClearingLicense;
use Fossology\Lib\Data\Clearing\ClearingResult;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Mockery as M;

function ReportCachePurgeAll()
{

}

class ClearingDecisionProcessorTest extends \PHPUnit_Framework_TestCase
{
  const MATCH_ID = 231;
  const PERCENTAGE = 315;
  /** @var int */
  private $uploadTreeId;

  /** @var int */
  private $userId;

  /** @var int */
  private $groupId;

  /** @var AgentLicenseEventProcessor|M\MockInterface */
  private $agentLicenseEventProcessor;

  /** @var DbManager|M\MockInterface */
  private $dbManager;

  /** @var AgentDao|M\MockInterface */
  private $agentsDao;

  /** @var ClearingDao|M\MockInterface */
  private $clearingDao;

  /** @var ItemTreeBounds|M\MockInterface */
  private $itemTreeBounds;

  /** @var ClearingDecisionProcessor */
  private $clearingDecisionProcessor;

  /** @var ClearingEventProcessor */
  private $clearingEventProcessor;

  public function setUp()
  {
    $this->uploadTreeId = 432;
    $this->pfileId = 32;
    $this->userId = 12;
    $this->groupId = 5;

    $this->clearingDao = M::mock(ClearingDao::classname());
    $this->agentLicenseEventProcessor = M::mock(AgentLicenseEventProcessor::classname());
    $this->clearingEventProcessor = new ClearingEventProcessor();

    $this->itemTreeBounds = M::mock(ItemTreeBounds::classname());
    $this->itemTreeBounds->shouldReceive("getItemId")->withNoArgs()->andReturn($this->uploadTreeId);
    $this->itemTreeBounds->shouldReceive("getPfileId")->withNoArgs()->andReturn($this->pfileId);

    $this->dbManager = M::mock(DbManager::classname());
    $this->dbManager->shouldReceive('isInTransaction')->withNoArgs()->andReturn(true);

    $this->clearingDecisionProcessor = new ClearingDecisionProcessor(
        $this->clearingDao, $this->agentLicenseEventProcessor, $this->clearingEventProcessor,
        $this->dbManager);
  }

  function tearDown()
  {
    M::close();
  }

  public function testMakeDecisionFromLastEvents()
  {
    $isGlobal = DecisionScopes::REPO;
    $addedEvent = $this->createClearingEvent(123, new DateTime(), 13, "licA", "License A");
    $addedLicense = $addedEvent->getClearingLicense();

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn(array($addedEvent));
    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
            ->with($this->itemTreeBounds)
            ->andReturn(array());

    $clearingDecision = M::mock(ClearingDecision::classname());
    $dateTime = new DateTime();
    $dateTime->sub(new \DateInterval("PT1H"));
    $clearingDecision->shouldReceive("getDateAdded")->withNoArgs()->andReturn($dateTime);
    $clearingDecision->shouldReceive("getType")->withNoArgs()->andReturn(DecisionTypes::IDENTIFIED);
    $clearingDecision->shouldReceive("getClearingEvents")->withNoArgs()->andReturn(array());

    $this->clearingDao->shouldReceive("getRelevantClearingDecision")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn($clearingDecision);

    $this->clearingDao->shouldReceive("createDecisionFromEvents")->once()
        ->with($this->uploadTreeId, $this->userId, $this->groupId, DecisionTypes::IDENTIFIED, $isGlobal, is(arrayContainingInAnyOrder(123)));

    $this->clearingDecisionProcessor->makeDecisionFromLastEvents($this->itemTreeBounds, $this->userId, $this->groupId, DecisionTypes::IDENTIFIED, $isGlobal);
  }

  public function testMakeDecisionFromLastEventsWithNoLicenseKnownTypeShouldNotCreateANewDecisionWhenNoLicensesShouldBeRemoved()
  {
    $isGlobal = DecisionScopes::UPLOAD;
    $addedEvent = $this->createClearingEvent(123, new DateTime(), 13, "licA", "License A");

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn(array($addedEvent));
    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
            ->with($this->itemTreeBounds)->andReturn(array());

    $clearingDecision = M::mock(ClearingDecision::classname());
    $dateTime = new DateTime();
    $dateTime->sub(new \DateInterval("PT1H"));
    $clearingDecision->shouldReceive("getDateAdded")->withNoArgs()->andReturn($dateTime);
    $clearingDecision->shouldReceive("getType")->withNoArgs()->andReturn(DecisionTypes::IDENTIFIED);
    $clearingDecision->shouldReceive("getClearingEvents")->withNoArgs()->andReturn(array());

    $this->clearingDao->shouldReceive("getRelevantClearingDecision")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn($clearingDecision);

    $this->clearingDao->shouldReceive("createDecisionFromEvents")->never();
    $this->clearingDao->shouldReceive("removeWipClearingDecision")->once()->with($this->uploadTreeId, $this->groupId);

    $this->clearingDecisionProcessor->makeDecisionFromLastEvents($this->itemTreeBounds, $this->userId, $this->groupId, ClearingDecisionProcessor::NO_LICENSE_KNOWN_DECISION_TYPE, $isGlobal);
  }

  public function testMakeDecisionFromLastEventsWithNoLicenseKnownType()
  {
    $isGlobal = true;
    
    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
            ->with($this->itemTreeBounds)->andReturn(array());

    $clearingDecision = M::mock(ClearingDecision::classname());
    $dateTime = new DateTime();
    $dateTime->sub(new DateInterval("PT1H"));
    $clearingDecision->shouldReceive("getDateAdded")->withNoArgs()->andReturn($dateTime);
    $clearingDecision->shouldReceive("getType")->withNoArgs()->andReturn(DecisionTypes::IRRELEVANT);
    $clearingDecision->shouldReceive("getClearingEvents")->withNoArgs()->andReturn(array());

    $this->clearingDao->shouldReceive("getRelevantClearingDecision")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn($clearingDecision);

    $this->clearingDao->shouldReceive("createDecisionFromEvents")->once()->with( $this->uploadTreeId, $this->userId, $this->groupId, DecisionTypes::IDENTIFIED, DecisionScopes::REPO, array());
    $this->clearingDao->shouldReceive("removeWipClearingDecision")->never();

    $this->clearingDecisionProcessor->makeDecisionFromLastEvents($this->itemTreeBounds, $this->userId, $this->groupId, ClearingDecisionProcessor::NO_LICENSE_KNOWN_DECISION_TYPE, $isGlobal);
  }

  /**
   * @brief user decides no license, then scanner finds licA, than user removes licA -> no new clearing event should be generated as nothing changes the state
   * @todo unsure
   */
  public function testMakeDecisionFromLastEventsWithDelayedScanner()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef) = $this->createScannerDetectedLicenses();

    $isGlobal = false;
    $removedEvent = $this->createClearingEvent(123, new DateTime(), $licenseRef->getId(), $licenseRef->getShortName(), $licenseRef->getFullName(), ClearingEventTypes::USER, $isRemoved = true);

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn(array($licenseRef->getId() => $removedEvent));

    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
            ->with($this->itemTreeBounds)->andReturn($scannerResults);

    $clearingDecision = M::mock(ClearingDecision::classname());
    $dateTime = new DateTime();
    $dateTime->sub(new \DateInterval("PT1H"));
    $clearingDecision->shouldReceive("getDateAdded")->withNoArgs()->andReturn($dateTime);
    $clearingDecision->shouldReceive("getType")->withNoArgs()->andReturn(DecisionTypes::IDENTIFIED);
    $clearingDecision->shouldReceive("getClearingEvents")->withNoArgs()->andReturn(array());

    $this->clearingDao->shouldReceive("getRelevantClearingDecision")
        ->with($this->itemTreeBounds,$this->groupId)
        ->andReturn($clearingDecision);

    $this->clearingDao->shouldReceive("insertClearingEvent")
            ->never();
         //   ->with($this->uploadTreeId, $this->userId, $this->groupId, $licenseRef->getId(), false, ClearingEventTypes::AGENT)
         //   ->andReturn($agentEventId=17);
    
    $this->clearingDao->shouldReceive("createDecisionFromEvents")
            ->once()
            ->with($this->uploadTreeId, $this->userId, $this->groupId, DecisionTypes::IDENTIFIED, DecisionScopes::ITEM,
                    is(arrayContainingInAnyOrder($removedEvent->getEventId())));
    $this->clearingDao->shouldReceive("removeWipClearingDecision")->never();

    $this->clearingDecisionProcessor->makeDecisionFromLastEvents($this->itemTreeBounds, $this->userId, $this->groupId, DecisionTypes::IDENTIFIED, $isGlobal);
  }

  public function testMakeDecisionFromLastEventsWithInvalidType()
  {
    $this->clearingDao->shouldReceive("getRelevantClearingEvents")->never();
    $this->agentLicenseEventProcessor->shouldReceive("getScannerDetectedLicenses")->never();
    $this->clearingDao->shouldReceive("getRelevantClearingDecision")->never();

    $this->clearingDao->shouldReceive("createDecisionFromEvents")->never();
    $this->clearingDao->shouldReceive("removeWipClearingDecision")->never();

    $this->clearingDecisionProcessor->makeDecisionFromLastEvents($this->itemTreeBounds, $this->userId, $this->groupId, ClearingDecisionProcessor::NO_LICENSE_KNOWN_DECISION_TYPE - 1, false);
  }

  public function testGetCurrentClearingsWithoutDecisions()
  {
    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")->with($this->itemTreeBounds)->andReturn(array());
    $this->clearingDao->shouldReceive("getRelevantClearingEvents")->with($this->itemTreeBounds, $this->groupId)->andReturn(array());

    list($licenseDecisions, $removedClearings) = $this->clearingDecisionProcessor->getCurrentClearings($this->itemTreeBounds, $this->groupId);

    assertThat($licenseDecisions, is(emptyArray()));
    assertThat($removedClearings, is(emptyArray()));
  }

  public function testGetCurrentClearingsWithUserDecisionsOnly()
  {
    $addedEvent = $this->createClearingEvent(123, new DateTime(), $licenseId=13, "licA", "License A");

    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")->with($this->itemTreeBounds)->andReturn(array());
    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn(array($licenseId => $addedEvent));

    list($licenseDecisions, $removedClearings) = $this->clearingDecisionProcessor->getCurrentClearings($this->itemTreeBounds, $this->groupId);

    assertThat($licenseDecisions, is(arrayWithSize(1)));

    /** @var ClearingResult $result */
    $result = $licenseDecisions[$addedEvent->getLicenseId()];
    assertThat($result->getLicenseRef(), is($addedEvent->getLicenseRef()));
    assertThat($result->getClearingEvent(), is($addedEvent));
    assertThat($result->getAgentDecisionEvents(), is(emptyArray()));
    assertThat($removedClearings, is(emptyArray()));
  }

  public function testGetCurrentClearingsWithAgentDecisionsOnly()
  {
    list($scannerResults, $licenseRef) = $this->createScannerDetectedLicenses();

    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
        ->with($this->itemTreeBounds)
        ->andReturn($scannerResults);
    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn(array());

    list($licenseDecisions, $removedClearings) = $this->clearingDecisionProcessor->getCurrentClearings($this->itemTreeBounds, $this->groupId);

    assertThat($licenseDecisions, is(arrayWithSize(1)));

    /** @var ClearingResult $result */
    $result = $licenseDecisions[$licenseRef->getId()];
    assertThat($result->getLicenseRef(), is($licenseRef));
    assertThat($result->getClearingEvent(), is(nullValue()));
    assertThat($result->getAgentDecisionEvents(), is(arrayWithSize(1)));
    assertThat($removedClearings, is(emptyArray()));
  }

  public function testGetCurrentClearingsWithUserAndAgentDecision()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef) = $this->createScannerDetectedLicenses();

    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
        ->with($this->itemTreeBounds)
        ->andReturn($scannerResults);

    $licenseId = $licenseRef->getId();
    
    $addedEvent = $this->createClearingEvent(123, new DateTime(), $licenseId, $licenseRef->getShortName(), $licenseRef->getFullName());
    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn(array($licenseId => $addedEvent));

    list($licenseDecisions, $removedClearings) = $this->clearingDecisionProcessor->getCurrentClearings($this->itemTreeBounds, $this->groupId);

    assertThat($licenseDecisions, is(arrayWithSize(1)));

    /** @var ClearingResult $result */
    $result = $licenseDecisions[$licenseRef->getId()];
    assertThat($result->getLicenseRef(), is($licenseRef));
    assertThat($result->getClearingEvent(), is($addedEvent));
    assertThat($result->getAgentDecisionEvents(), is(arrayWithSize(1)));
    assertThat($removedClearings, is(emptyArray()));
  }

  public function testGetCurrentClearingsWithUserRemovedDecisionsOnly()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef, $agentRef) = $this->createScannerDetectedLicenses();
    $removedEvent = $this->createClearingEvent(123, new DateTime(), $licenseRef->getId(), $licenseRef->getShortName(), $licenseRef->getFullName(), ClearingEventTypes::USER, true);

    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
        ->with($this->itemTreeBounds)->andReturn($scannerResults);

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn(array($licenseRef->getId() => $removedEvent));

    list($licenseDecisions, $removedClearings) = $this->clearingDecisionProcessor->getCurrentClearings($this->itemTreeBounds, $this->groupId);

    assertThat($licenseDecisions, is(emptyArray()));
    assertThat($removedClearings, is(arrayWithSize(1)));

    /** @var ClearingResult $result */
    $result = $removedClearings[$removedEvent->getLicenseId()];
    assertThat($result->getLicenseRef(), is($removedEvent->getLicenseRef()));
    assertThat($result->getClearingEvent(), is($removedEvent));
    $agentClearingEvents = $result->getAgentDecisionEvents();
    assertThat($agentClearingEvents, is(arrayWithSize(1)));

    $agentEvent = $agentClearingEvents[0];

    assertThat($agentEvent->getAgentRef(), is($agentRef));
    assertThat($agentEvent->getLicenseRef(), is($licenseRef));
    assertThat($agentEvent->getMatchId(), is(self::MATCH_ID));
    assertThat($agentEvent->getPercentage(), is(self::PERCENTAGE));
  }

  public function testGetUnhandledScannerDetectedLicenses()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef) = $this->createScannerDetectedLicenses();

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn(array());
    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
        ->with($this->itemTreeBounds)->andReturn($scannerResults);

    $unhandledScannerDetectedLicenses = $this->clearingDecisionProcessor->getUnhandledScannerDetectedLicenses($this->itemTreeBounds, $this->groupId);

    assertThat($unhandledScannerDetectedLicenses, is(array($licenseRef->getId() => $licenseRef)));
  }

  public function testGetUnhandledScannerDetectedLicensesWithMatch()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef) = $this->createScannerDetectedLicenses();
    $clearingEvent = $this->createClearingEvent(123, new DateTime(), $licenseRef->getId(), $licenseRef->getShortName(), $licenseRef->getFullName());

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn(array($clearingEvent));
    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
        ->with($this->itemTreeBounds)->andReturn($scannerResults);

    $unhandledScannerDetectedLicenses = $this->clearingDecisionProcessor->getUnhandledScannerDetectedLicenses($this->itemTreeBounds, $this->groupId);

    assertThat($unhandledScannerDetectedLicenses, is(emptyArray()));
  }

  public function testGetUnhandledScannerDetectedLicensesWithoutMatch()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef) = $this->createScannerDetectedLicenses();
    $clearingEvent = $this->createClearingEvent(123, new DateTime(), 321, "licB", "License-B");

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn(array($clearingEvent));
    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
        ->with($this->itemTreeBounds)->andReturn($scannerResults);

    $unhandledScannerDetectedLicenses = $this->clearingDecisionProcessor->getUnhandledScannerDetectedLicenses($this->itemTreeBounds, $this->groupId);

    assertThat($unhandledScannerDetectedLicenses, is(array($licenseRef->getId() => $licenseRef)));
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
  protected function createScannerDetectedLicenses($licenseId = 13, $licenseShortname = "licA", $licenseFullName = "License-A")
  {
    $licenseRef = new LicenseRef($licenseId, $licenseShortname, $licenseFullName);

    $agentRef = M::mock(AgentRef::classname());
    
    $scannerEvents = array(
      $licenseId => array(new AgentClearingEvent($licenseRef, $agentRef, self::MATCH_ID, self::PERCENTAGE))
    );

    return array($scannerEvents, $licenseRef, $agentRef);
  }

}
