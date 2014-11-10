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

use DateTime;
use Fossology\Lib\Dao\AgentsDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingResult;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Mockery as M;


class ClearingDecisionEventProcessorTest extends \PHPUnit_Framework_TestCase
{
  /** @var int */
  private $uploadTreeId;

  /** @var int */
  private $userId;

  /** @var int */
  private $groupId;

  /** @var AgentLicenseEventProcessor|M\MockInterface */
  private $agentLicenseEventProcessor;

  /** @var AgentsDao|M\MockInterface */
  private $agentsDao;

  /** @var ClearingDao|M\MockInterface */
  private $clearingDao;

  /** @var ItemTreeBounds|M\MockInterface */
  private $itemTreeBounds;

  /** @var ClearingDecisionEventProcessor */
  private $clearingDecisionEventProcessor;

  public function setUp()
  {
    $this->uploadTreeId = 432;
    $this->userId = 12;
    $this->groupId = 5;

    $this->clearingDao = M::mock(ClearingDao::classname());
    $this->agentLicenseEventProcessor = M::mock(AgentLicenseEventProcessor::classname());

    $this->itemTreeBounds = M::mock(ItemTreeBounds::classname());
    $this->itemTreeBounds->shouldReceive("getUploadTreeId")->withNoArgs()->andReturn($this->uploadTreeId);

    $this->clearingDecisionEventProcessor = new ClearingDecisionEventProcessor($this->clearingDao, $this->agentLicenseEventProcessor);
  }

  public function testGetCurrentClearingsWithoutDecisions()
  {
    $this->agentLicenseEventProcessor->shouldReceive("getLatestAgentDetectedLicenses")->with($this->itemTreeBounds)->andReturn(array());
    $this->clearingDao->shouldReceive("getCurrentClearings")->with($this->userId, $this->uploadTreeId)->andReturn(array(array(), array()));

    list($licenseDecisions, $removedClearings) = $this->clearingDecisionEventProcessor->getCurrentClearings($this->itemTreeBounds, $this->userId);

    assertThat($licenseDecisions, is(emptyArray()));
    assertThat($removedClearings, is(emptyArray()));
  }

  public function testGetCurrentClearingsWithUserDecisionsOnly()
  {
    $addedEvent = $this->createClearingEvent(123, 13, "licA", "License A");

    $this->agentLicenseEventProcessor->shouldReceive("getLatestAgentDetectedLicenses")->with($this->itemTreeBounds)->andReturn(array());
    $this->clearingDao->shouldReceive("getCurrentClearings")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array($this->createResults($addedEvent), array()));

    list($licenseDecisions, $removedClearings) = $this->clearingDecisionEventProcessor->getCurrentClearings($this->itemTreeBounds, $this->userId);

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
    $agentRef = new AgentRef(143, "agent", "1.1");
    $licenseRef = new LicenseRef(13, "licA", "License A");
    $addedEvents = array(
        "licA" => array(
            $agentRef->getAgentName() => array(
                array(
                    "id" => $licenseRef->getId(),
                    "licenseRef" => $licenseRef,
                    "agentRef" => $agentRef,
                    "matchId" => 143,
                    "percentage" => 98
                )
            )
        )
    );

    $this->agentLicenseEventProcessor->shouldReceive("getLatestAgentDetectedLicenses")
        ->with($this->itemTreeBounds)
        ->andReturn($addedEvents);
    $this->clearingDao->shouldReceive("getCurrentClearings")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array(array(), array()));

    list($licenseDecisions, $removedClearings) = $this->clearingDecisionEventProcessor->getCurrentClearings($this->itemTreeBounds, $this->userId);

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
    $agentRef = new AgentRef(143, "agent", "1.1");
    $licenseRef = new LicenseRef(13, "licA", "License A");
    $addedEvents = array(
        "licA" => array(
            $agentRef->getAgentName() => array(
                array(
                    "id" => $licenseRef->getId(),
                    "licenseRef" => $licenseRef,
                    "agentRef" => $agentRef,
                    "matchId" => 143,
                    "percentage" => 98
                )
            )
        )
    );

    $this->agentLicenseEventProcessor->shouldReceive("getLatestAgentDetectedLicenses")
        ->with($this->itemTreeBounds)
        ->andReturn($addedEvents);

    $addedEvent = $this->createClearingEvent(123, 13, "licA", "License A");
    $this->clearingDao->shouldReceive("getCurrentClearings")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array($this->createResults($addedEvent), array()));

    list($licenseDecisions, $removedClearings) = $this->clearingDecisionEventProcessor->getCurrentClearings($this->itemTreeBounds, $this->userId);

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
    $removedEvent = $this->createClearingEvent(123, 13, "licA", "License A");

    $agentRef = new AgentRef(143, "agent", "1.1");
    $licenseRef = new LicenseRef(13, "licA", "License A");
    $addedAgentEvents = array(
        "licA" => array(
            $agentRef->getAgentName() => array(
                array(
                    "id" => $licenseRef->getId(),
                    "licenseRef" => $licenseRef,
                    "agentRef" => $agentRef,
                    "matchId" => 143,
                    "percentage" => 98
                )
            )
        )
    );
    $this->agentLicenseEventProcessor->shouldReceive("getLatestAgentDetectedLicenses")
        ->with($this->itemTreeBounds)->andReturn($addedAgentEvents);
    $this->clearingDao->shouldReceive("getCurrentClearings")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array(array(), $this->createResults($removedEvent)));

    list($licenseDecisions, $removedClearings) = $this->clearingDecisionEventProcessor->getCurrentClearings($this->itemTreeBounds, $this->userId);

    assertThat($licenseDecisions, is(emptyArray()));

    assertThat($removedClearings, is(arrayWithSize(1)));

    /** @var ClearingResult $result */
    $result = $removedClearings[$removedEvent->getLicenseShortName()];
    assertThat($result->getLicenseRef(), is($removedEvent->getLicenseRef()));
    assertThat($result->getClearingEvent(), is(nullValue()));
    $agentClearingEvents = $result->getAgentDecisionEvents();
    assertThat($agentClearingEvents, is(arrayWithSize(1)));

    $agentEvent = $agentClearingEvents[0];

    assertThat($agentEvent->getAgentRef(), is($agentRef));
    assertThat($agentEvent->getLicenseRef(), is($licenseRef));
    assertThat($agentEvent->getMatchId(), is(143));
    assertThat($agentEvent->getPercentage(), is(98));
  }

  /**
   * @param $eventId
   * @param $licenseId
   * @param $licenseShortName
   * @param $licenseFullName
   * @param string $eventType
   * @param bool $isRemoved
   * @param string $reportInfo
   * @param string $comment
   * @return ClearingEvent
   */
  private function createClearingEvent($eventId, $licenseId, $licenseShortName, $licenseFullName, $eventType = ClearingEventTypes::USER, $isRemoved = false, $reportInfo = "<reportInfo>", $comment = "<comment>")
  {
    $licenseRef = new LicenseRef($licenseId, $licenseShortName, $licenseFullName);
    return new ClearingEvent($eventId, $this->uploadTreeId, new DateTime(), $this->userId, $this->groupId, $eventType, $licenseRef, $isRemoved, $reportInfo, $comment);
  }

  private function createResults()
  {
    $result = array();
    foreach (func_get_args() as $licenseDecisionEvent)
    {
      /** @var $licenseDecisionEvent ClearingEvent */
      $result[$licenseDecisionEvent->getLicenseShortName()] = $licenseDecisionEvent;
    }
    return $result;
  }

}

 