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

use Fossology\Lib\Dao\AgentsDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Mockery as M;

class ClearingDecisionEventProcessorTest extends \PHPUnit_Framework_TestCase
{
  /** @var int */
  protected $uploadTreeId;

  /** @var int */
  protected $userId;

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

    $this->clearingDao = M::mock(ClearingDao::classname());
    $this->agentLicenseEventProcessor = M::mock(AgentLicenseEventProcessor::classname());

    $this->itemTreeBounds = M::mock(ItemTreeBounds::classname());

    $this->clearingDecisionEventProcessor = new ClearingDecisionEventProcessor($this->clearingDao, $this->agentLicenseEventProcessor);
  }

  public function testGetCurrentLicenseDecisionsWithoutDecisions()
  {

    $itemTreeBounds = M::mock(ItemTreeBounds::classname());
    $itemTreeBounds->shouldReceive("getUploadTreeId")->withNoArgs()->andReturn($this->uploadTreeId);

    $this->agentLicenseEventProcessor->shouldReceive("getLatestAgentDetectedLicenses")->with($itemTreeBounds)->andReturn(array());
    $this->clearingDao->shouldReceive("getCurrentLicenseDecisions")->with($this->userId, $this->uploadTreeId)->andReturn(array(array(), array()));

    list($licenseDecisions, $removedLicenseDecisions) = $this->clearingDecisionEventProcessor->getCurrentLicenseDecisions($itemTreeBounds, $this->userId);

    assertThat($licenseDecisions, is(emptyArray()));
    assertThat($removedLicenseDecisions, is(emptyArray()));
  }

}

 