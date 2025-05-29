<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Clearing\AgentClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\Clearing\ClearingLicense;
use Fossology\Lib\Data\Clearing\ClearingResult;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Mockery as M;

function ReportCachePurgeAll()
{

}

class ClearingDecisionProcessorTest extends \PHPUnit\Framework\TestCase
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
  /** @var ClearingDao|M\MockInterface */
  private $clearingDao;
  /** @var ItemTreeBounds|M\MockInterface */
  private $itemTreeBounds;
  /** @var ClearingDecisionProcessor */
  private $clearingDecisionProcessor;
  /** @var ClearingEventProcessor */
  private $clearingEventProcessor;
  /** @var int */
  private $timestamp;
  /** @var boolean */
  private $includeSubFolders;
  /** @var boolean */
  private $includeExpressions;

  protected function setUp() : void
  {
    $this->uploadTreeId = 432;
    $this->pfileId = 32;
    $this->userId = 12;
    $this->groupId = 5;
    $this->timestamp = time();
    $this->includeSubFolders = false;
    $this->includeExpressions = true;

    $this->clearingDao = M::mock(ClearingDao::class);
    $this->agentLicenseEventProcessor = M::mock(AgentLicenseEventProcessor::class);
    $this->clearingEventProcessor = new ClearingEventProcessor();

    $this->itemTreeBounds = M::mock(ItemTreeBounds::class);
    $this->itemTreeBounds->shouldReceive("getItemId")->withNoArgs()->andReturn($this->uploadTreeId);
    $this->itemTreeBounds->shouldReceive("getPfileId")->withNoArgs()->andReturn($this->pfileId);

    $this->dbManager = M::mock(DbManager::class);
    $this->dbManager->shouldReceive('begin')->withNoArgs();
    $this->dbManager->shouldReceive('commit')->withNoArgs();

    $this->clearingDecisionProcessor = new ClearingDecisionProcessor(
        $this->clearingDao, $this->agentLicenseEventProcessor, $this->clearingEventProcessor,
        $this->dbManager);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    M::close();
  }

  public function testMakeDecisionFromLastEvents()
  {
    $isGlobal = DecisionScopes::ITEM;
    $addedEvent = $this->createClearingEvent(123, $this->timestamp, 13, "licA", "License A");

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId, $this->includeSubFolders)
        ->andReturn(array($addedEvent));
    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
            ->with($this->itemTreeBounds, LicenseMap::TRIVIAL, $this->includeExpressions)
            ->andReturn(array());

    $clearingDecision = M::mock(ClearingDecision::class);
    $clearingDecision->shouldReceive("getTimeStamp")->withNoArgs()->andReturn($this->timestamp-3600);
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
    $isGlobal = DecisionScopes::REPO;
    $addedEvent = $this->createClearingEvent(123, $this->timestamp, 13, "licA", "License A");

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId, $this->includeSubFolders)
        ->andReturn(array($addedEvent));
    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
            ->with($this->itemTreeBounds, LicenseMap::TRIVIAL, $this->includeExpressions)->andReturn(array());

    $clearingDecision = M::mock(ClearingDecision::class);
    $clearingDecision->shouldReceive("getTimeStamp")->withNoArgs()->andReturn($this->timestamp-3600);
    $clearingDecision->shouldReceive("getType")->withNoArgs()->andReturn(DecisionTypes::IDENTIFIED);
    $clearingDecision->shouldReceive("getScope")->withNoArgs()->andReturn(DecisionScopes::ITEM);
    $clearingDecision->shouldReceive("getClearingEvents")->withNoArgs()->andReturn(array());

    $this->clearingDao->shouldReceive("getRelevantClearingDecision")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn($clearingDecision);
    $eventId = 65;
    $this->clearingDao->shouldReceive("insertClearingEvent")->with($this->itemTreeBounds->getItemId(), $this->userId, $this->groupId, $addedEvent->getLicenseId(), true)->andReturn($eventId);

    $this->clearingDao->shouldReceive("createDecisionFromEvents")->once()->with($this->uploadTreeId, $this->userId, $this->groupId, DecisionTypes::IDENTIFIED, $isGlobal, array($eventId));
    $this->clearingDao->shouldReceive("removeWipClearingDecision")->never();

    $this->clearingDecisionProcessor->makeDecisionFromLastEvents($this->itemTreeBounds, $this->userId, $this->groupId, ClearingDecisionProcessor::NO_LICENSE_KNOWN_DECISION_TYPE, $isGlobal);
  }

  public function testMakeDecisionFromLastEventsWithNoLicenseKnownTypeShouldNotCreateANewDecisionWhenNoLicensesShouldBeRemovedAndTheScopeDoesNotChange()
  {
    $isGlobal = DecisionScopes::REPO;
    $addedEvent = $this->createClearingEvent(123, $this->timestamp, 13, "licA", "License A");

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId, $this->includeSubFolders)
        ->andReturn(array($addedEvent));
    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
            ->with($this->itemTreeBounds, LicenseMap::TRIVIAL, $this->includeExpressions)->andReturn(array());

    $clearingDecision = M::mock(ClearingDecision::class);
    $clearingDecision->shouldReceive("getTimeStamp")->withNoArgs()->andReturn($this->timestamp-3600);
    $clearingDecision->shouldReceive("getType")->withNoArgs()->andReturn(DecisionTypes::IDENTIFIED);
    $clearingDecision->shouldReceive("getScope")->withNoArgs()->andReturn($isGlobal);
    $clearingDecision->shouldReceive("getClearingEvents")->withNoArgs()->andReturn(array());

    $this->clearingDao->shouldReceive("getRelevantClearingDecision")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn($clearingDecision);

    $eventId = 65;
    $this->clearingDao->shouldReceive("insertClearingEvent")->with($this->itemTreeBounds->getItemId(), $this->userId, $this->groupId, $addedEvent->getLicenseId(), true)->andReturn($eventId);
    $this->clearingDao->shouldReceive("createDecisionFromEvents")->once()->with($this->uploadTreeId, $this->userId, $this->groupId, DecisionTypes::IDENTIFIED, $isGlobal, array($eventId));
    $this->clearingDao->shouldReceive("removeWipClearingDecision")->never();

    $this->clearingDecisionProcessor->makeDecisionFromLastEvents($this->itemTreeBounds, $this->userId, $this->groupId, ClearingDecisionProcessor::NO_LICENSE_KNOWN_DECISION_TYPE, $isGlobal);
  }

  public function testMakeDecisionFromLastEventsWithNoLicenseKnownType()
  {
    $isGlobal = true;

    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
            ->with($this->itemTreeBounds, LicenseMap::TRIVIAL, $this->includeExpressions)->andReturn(array());

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId, $this->includeSubFolders)
        ->andReturn(array());

    $clearingDecision = M::mock(ClearingDecision::class);
    $clearingDecision->shouldReceive("getTimeStamp")->withNoArgs()->andReturn($this->timestamp-3600);
    $clearingDecision->shouldReceive("getType")->withNoArgs()->andReturn(DecisionTypes::IRRELEVANT);
    $clearingDecision->shouldReceive("getClearingEvents")->withNoArgs()->andReturn(array());

    $this->clearingDao->shouldReceive("getRelevantClearingDecision")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn($clearingDecision);

    $this->clearingDao->shouldReceive("createDecisionFromEvents")->once()->with( $this->uploadTreeId, $this->userId, $this->groupId, DecisionTypes::IDENTIFIED, DecisionScopes::REPO, array());
    $this->clearingDao->shouldReceive("removeWipClearingDecision")->never();

    $this->clearingDecisionProcessor->makeDecisionFromLastEvents($this->itemTreeBounds, $this->userId, $this->groupId, ClearingDecisionProcessor::NO_LICENSE_KNOWN_DECISION_TYPE, $isGlobal);
  }

  public function testMakeDecisionFromLastEventsWithNoLicenseKnownTypeAndAnExistingAddingUserEvent()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef) = $this->createScannerDetectedLicenses();
    $addedEvent = $this->createClearingEvent(123, $this->timestamp, $licenseRef->getId(), $licenseRef->getShortName(), $licenseRef->getFullName(), ClearingEventTypes::USER, $isRemoved = false);

    $isGlobal = true;

    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
        ->with($this->itemTreeBounds, LicenseMap::TRIVIAL, $this->includeExpressions)->andReturn(array());

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId, $this->includeSubFolders)
        ->andReturn(array($licenseRef->getId() => $addedEvent));

    $clearingDecision = M::mock(ClearingDecision::class);
    $clearingDecision->shouldReceive("getTimeStamp")->withNoArgs()->andReturn($this->timestamp-3600);
    $clearingDecision->shouldReceive("getType")->withNoArgs()->andReturn(DecisionTypes::IRRELEVANT);
    $clearingDecision->shouldReceive("getClearingEvents")->withNoArgs()->andReturn(array());

    $this->clearingDao->shouldReceive("getRelevantClearingDecision")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn($clearingDecision);

    $eventId = 65;
    $this->clearingDao->shouldReceive("insertClearingEvent")->with($this->itemTreeBounds->getItemId(), $this->userId, $this->groupId, $addedEvent->getLicenseId(), true)->andReturn($eventId);
    $this->clearingDao->shouldReceive("createDecisionFromEvents")->once()->with( $this->uploadTreeId, $this->userId, $this->groupId, DecisionTypes::IDENTIFIED, DecisionScopes::REPO, array($eventId));
    $this->clearingDao->shouldReceive("removeWipClearingDecision")->never();

    $this->clearingDecisionProcessor->makeDecisionFromLastEvents($this->itemTreeBounds, $this->userId, $this->groupId, ClearingDecisionProcessor::NO_LICENSE_KNOWN_DECISION_TYPE, $isGlobal);
  }

  public function testMakeDecisionFromLastEventsWithNoLicenseKnownTypeAndAnExistingRemovingUserEvent()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef) = $this->createScannerDetectedLicenses();
    $removedEvent = $this->createClearingEvent(123, $this->timestamp, $licenseRef->getId(), $licenseRef->getShortName(), $licenseRef->getFullName(), ClearingEventTypes::USER, $isRemoved = true);

    $isGlobal = true;

    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
        ->with($this->itemTreeBounds, LicenseMap::TRIVIAL, $this->includeExpressions)->andReturn(array());

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId, $this->includeSubFolders)
        ->andReturn(array($licenseRef->getId() => $removedEvent));

    $clearingDecision = M::mock(ClearingDecision::class);
    $clearingDecision->shouldReceive("getTimeStamp")->withNoArgs()->andReturn($this->timestamp-3600);
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
   */
  public function testMakeDecisionFromLastEventsWithDelayedScanner()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef) = $this->createScannerDetectedLicenses();

    $isGlobal = false;
    $removedEvent = $this->createClearingEvent(123, $this->timestamp, $licenseRef->getId(), $licenseRef->getShortName(), $licenseRef->getFullName(), ClearingEventTypes::USER, $isRemoved = true);

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId, $this->includeSubFolders)
        ->andReturn(array($licenseRef->getId() => $removedEvent));

    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
            ->with($this->itemTreeBounds, LicenseMap::TRIVIAL, $this->includeExpressions)->andReturn($scannerResults);

    $clearingDecision = M::mock(ClearingDecision::class);
    $clearingDecision->shouldReceive("getTimeStamp")->withNoArgs()->andReturn($this->timestamp-3600);
    $clearingDecision->shouldReceive("getType")->withNoArgs()->andReturn(DecisionTypes::IDENTIFIED);
    $clearingDecision->shouldReceive("getClearingEvents")->withNoArgs()->andReturn(array());

    $this->clearingDao->shouldReceive("getRelevantClearingDecision")
        ->with($this->itemTreeBounds,$this->groupId)
        ->andReturn($clearingDecision);

    $this->clearingDao->shouldReceive("insertClearingEvent")
            ->never();

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
    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
            ->with($this->itemTreeBounds,LicenseMap::TRIVIAL, false)
            ->andReturn(array());
    $this->clearingDao->shouldReceive("getRelevantClearingEvents")->with($this->itemTreeBounds, $this->groupId, true, false)->andReturn(array());

    list($licenseDecisions, $removedClearings) = $this->clearingDecisionProcessor->getCurrentClearings($this->itemTreeBounds, $this->groupId);

    assertThat($licenseDecisions, is(emptyArray()));
    assertThat($removedClearings, is(emptyArray()));
  }

  public function testGetCurrentClearingsWithUserDecisionsOnly()
  {
    $addedEvent = $this->createClearingEvent(123, $this->timestamp, $licenseId=13, "licA", "License A");

    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
            ->with($this->itemTreeBounds,LicenseMap::TRIVIAL,false)
            ->andReturn(array());
    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId, true, false)
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
        ->with($this->itemTreeBounds,LicenseMap::TRIVIAL,false)
        ->andReturn($scannerResults);
    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId, true, false)
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
        ->with($this->itemTreeBounds,LicenseMap::TRIVIAL,false)
        ->andReturn($scannerResults);

    $licenseId = $licenseRef->getId();

    $addedEvent = $this->createClearingEvent(123, $this->timestamp, $licenseId, $licenseRef->getShortName(), $licenseRef->getFullName());
    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId, true, false)
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
    $removedEvent = $this->createClearingEvent(123, $this->timestamp, $licenseRef->getId(), $licenseRef->getShortName(), $licenseRef->getFullName(), ClearingEventTypes::USER, true);

    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
        ->with($this->itemTreeBounds,LicenseMap::TRIVIAL,false)->andReturn($scannerResults);

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId, true, false)
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
        ->with($this->itemTreeBounds,LicenseMap::TRIVIAL)->andReturn($scannerResults);

    $hasUnhandledScannerDetectedLicenses = $this->clearingDecisionProcessor->hasUnhandledScannerDetectedLicenses($this->itemTreeBounds, $this->groupId);

    assertThat($hasUnhandledScannerDetectedLicenses);
  }

  public function testGetUnhandledScannerDetectedLicensesWithMatch()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef) = $this->createScannerDetectedLicenses();
    $clearingEvent = $this->createClearingEvent(123, $this->timestamp, $licenseRef->getId(), $licenseRef->getShortName(), $licenseRef->getFullName());

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")->once()
         ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn(array($clearingEvent->getLicenseId()=>$clearingEvent));
    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")->once()
        ->with($this->itemTreeBounds,LicenseMap::TRIVIAL)->andReturn($scannerResults);

    $hasUnhandledScannerDetectedLicenses = $this->clearingDecisionProcessor->hasUnhandledScannerDetectedLicenses($this->itemTreeBounds, $this->groupId);

    assertThat( $hasUnhandledScannerDetectedLicenses, is(False));
  }

  public function testGetUnhandledScannerDetectedLicensesWithoutMatch()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef) = $this->createScannerDetectedLicenses();
    $offset = 23;
    $clearingEvent = $this->createClearingEvent(123, $this->timestamp, $licenseRef->getId()+$offset, $licenseRef->getShortName(), $licenseRef->getFullName());

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn(array($clearingEvent->getLicenseId()=>$clearingEvent));
    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
        ->with($this->itemTreeBounds,LicenseMap::TRIVIAL)->andReturn($scannerResults);

    $hasUnhandledScannerDetectedLicenses = $this->clearingDecisionProcessor->hasUnhandledScannerDetectedLicenses($this->itemTreeBounds, $this->groupId);

    assertThat($hasUnhandledScannerDetectedLicenses, is(True));
  }

  public function testGetUnhandledScannerDetectedLicensesWithMappedMatch()
  {
    /** @var LicenseRef $licenseRef */
    list($scannerResults, $licenseRef) = $this->createScannerDetectedLicenses();
    $offset = 0;
    $clearingEvent = $this->createClearingEvent($eventId=123, $this->timestamp, $licenseRef->getId()+$offset, $licenseRef->getShortName(), $licenseRef->getFullName());

    $this->clearingDao->shouldReceive("getRelevantClearingEvents")
        ->with($this->itemTreeBounds, $this->groupId)
        ->andReturn(array($clearingEvent->getLicenseId()=>$clearingEvent));
    $this->agentLicenseEventProcessor->shouldReceive("getScannerEvents")
        ->with($this->itemTreeBounds,LicenseMap::CONCLUSION)->andReturn($scannerResults);

    $licenseMap = M::mock(LicenseMap::class);
    $licenseMap->shouldReceive('getProjectedId')->andReturnUsing(function($id) {
      return $id;
    });
    $licenseMap->shouldReceive('getUsage')->andReturn(LicenseMap::CONCLUSION);

    $hasUnhandledScannerDetectedLicenses = $this->clearingDecisionProcessor->hasUnhandledScannerDetectedLicenses($this->itemTreeBounds, $this->groupId, array(), $licenseMap);

    assertThat( $hasUnhandledScannerDetectedLicenses, is(False) );
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
    $licenseRef = new LicenseRef($licenseId, $licenseShortName, $licenseFullName, $licenseShortName);
    $clearingLicense = new ClearingLicense($licenseRef, $isRemoved, $reportInfo, $comment);
    return new ClearingEvent($eventId, $this->uploadTreeId, $timestamp, $this->userId, $this->groupId, $eventType, $clearingLicense);
  }

  /**
   * @return array
   */
  protected function createScannerDetectedLicenses($licenseId = 13, $licenseShortname = "licA", $licenseFullName = "License-A")
  {
    $licenseRef = new LicenseRef($licenseId, $licenseShortname, $licenseFullName, $licenseShortname);

    $agentRef = M::mock(AgentRef::class);

    $scannerEvents = array(
      $licenseId => array(new AgentClearingEvent($licenseRef, $agentRef, self::MATCH_ID, self::PERCENTAGE))
    );

    return array($scannerEvents, $licenseRef, $agentRef);
  }
}
