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
use Fossology\Lib\Data\LicenseDecision\LicenseDecisionEvent;
use Fossology\Lib\Data\LicenseDecision\LicenseDecisionResult;
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

  public function testGetCurrentLicenseDecisionsWithoutDecisions()
  {
    $this->agentLicenseEventProcessor->shouldReceive("getLatestAgentDetectedLicenses")->with($this->itemTreeBounds)->andReturn(array());
    $this->clearingDao->shouldReceive("getCurrentLicenseDecisions")->with($this->userId, $this->uploadTreeId)->andReturn(array(array(), array()));

    list($licenseDecisions, $removedLicenseDecisions) = $this->clearingDecisionEventProcessor->getCurrentLicenseDecisions($this->itemTreeBounds, $this->userId);

    assertThat($licenseDecisions, is(emptyArray()));
    assertThat($removedLicenseDecisions, is(emptyArray()));
  }

  public function testGetCurrentLicenseDecisionsWithUserDecisionsOnly()
  {
    $addedEvent = $this->createLicenseDecisionEvent(123, 12, 13, "licA", "License A");

    $this->agentLicenseEventProcessor->shouldReceive("getLatestAgentDetectedLicenses")->with($this->itemTreeBounds)->andReturn(array());
    $this->clearingDao->shouldReceive("getCurrentLicenseDecisions")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array($this->createResults($addedEvent), array()));

    list($licenseDecisions, $removedLicenseDecisions) = $this->clearingDecisionEventProcessor->getCurrentLicenseDecisions($this->itemTreeBounds, $this->userId);

    assertThat($licenseDecisions, is(arrayWithSize(1)));

    /** @var LicenseDecisionResult $result */
    $result = $licenseDecisions[$addedEvent->getLicenseShortName()];
    assertThat($result->getLicenseRef(), is($addedEvent->getLicenseRef()));
    assertThat($result->getLicenseDecisionEvent(), is($addedEvent));
    assertThat($result->getAgentDecisionEvents(), is(emptyArray()));
    assertThat($removedLicenseDecisions, is(emptyArray()));
  }

  public function testGetCurrentLicenseDecisionsWithAgentDecisionsOnly()
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
    $this->clearingDao->shouldReceive("getCurrentLicenseDecisions")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array(array(), array()));

    list($licenseDecisions, $removedLicenseDecisions) = $this->clearingDecisionEventProcessor->getCurrentLicenseDecisions($this->itemTreeBounds, $this->userId);

    assertThat($licenseDecisions, is(arrayWithSize(1)));

    /** @var LicenseDecisionResult $result */
    $result = $licenseDecisions[$licenseRef->getShortName()];
    assertThat($result->getLicenseRef(), is($licenseRef));
    assertThat($result->getLicenseDecisionEvent(), is(nullValue()));
    assertThat($result->getAgentDecisionEvents(), is(arrayWithSize(1)));
    assertThat($removedLicenseDecisions, is(emptyArray()));
  }

  public function testGetCurrentLicenseDecisionsWithUserAndAgentDecision()
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

    $addedEvent = $this->createLicenseDecisionEvent(123, 12, 13, "licA", "License A");
    $this->clearingDao->shouldReceive("getCurrentLicenseDecisions")
        ->with($this->userId, $this->uploadTreeId)
        ->andReturn(array($this->createResults($addedEvent), array()));

    list($licenseDecisions, $removedLicenseDecisions) = $this->clearingDecisionEventProcessor->getCurrentLicenseDecisions($this->itemTreeBounds, $this->userId);

    assertThat($licenseDecisions, is(arrayWithSize(1)));

    /** @var LicenseDecisionResult $result */
    $result = $licenseDecisions[$licenseRef->getShortName()];
    assertThat($result->getLicenseRef(), is($licenseRef));
    assertThat($result->getLicenseDecisionEvent(), is($addedEvent));
    assertThat($result->getAgentDecisionEvents(), is(arrayWithSize(1)));
    assertThat($removedLicenseDecisions, is(emptyArray()));
  }

  /**
   * @param $eventId
   * @param $fileId
   * @param $licenseId
   * @param $licenseShortName
   * @param $licenseFullName
   * @param string $eventType
   * @param bool $isGlobal
   * @param bool $isRemoved
   * @param string $reportInfo
   * @param string $comment
   * @return LicenseDecisionEvent
   */
  private function createLicenseDecisionEvent($eventId, $fileId, $licenseId, $licenseShortName, $licenseFullName, $eventType = LicenseDecisionEvent::USER_DECISION, $isGlobal = false, $isRemoved = false, $reportInfo = "<reportInfo>", $comment = "<comment>")
  {
    $licenseRef = new LicenseRef($licenseId, $licenseShortName, $licenseFullName);
    return new LicenseDecisionEvent($eventId, $fileId, $this->uploadTreeId, new DateTime(), $this->userId, $this->groupId, $eventType, $licenseRef, $isGlobal, $isRemoved, $reportInfo, $comment);
  }

  private function createResults()
  {
    $result = array();
    foreach (func_get_args() as $licenseDecisionEvent)
    {
      /** @var $licenseDecisionEvent LicenseDecisionEvent */
      $result[$licenseDecisionEvent->getLicenseShortName()] = $licenseDecisionEvent;
    }
    return $result;
  }

}

 