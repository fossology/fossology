<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\LicenseMatch;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Mockery as M;

class AgentLicenseEventProcessorTest extends \PHPUnit\Framework\TestCase
{
  /** @var LicenseDao|M\MockInterface */
  private $licenseDao;
  /** @var AgentDao|M\MockInterface */
  private $agentsDao;
  /** @var ItemTreeBounds|M\MockInterface */
  private $itemTreeBounds;
  /** @var AgentLicenseEventProcessor */
  private $agentLicenseEventProcessor;
  private $dbManagerMock;
  private $latestScanners = array(array('agent_pk'=>23,'agent_name'=>'nomos'),
                    array('agent_pk'=>22,'agent_name'=>'monk'));

  protected function setUp() : void
  {
    $this->licenseDao = M::mock(LicenseDao::class);
    $this->agentsDao = M::mock(AgentDao::class);

    $this->itemTreeBounds = M::mock(ItemTreeBounds::class);

    $this->agentLicenseEventProcessor = new AgentLicenseEventProcessor($this->licenseDao, $this->agentsDao);

    global $container;
    $this->dbManagerMock = M::mock(DbManager::class);
    $this->dbManagerMock->shouldReceive('prepare');
    $this->dbManagerMock->shouldReceive('execute');
    $this->dbManagerMock->shouldReceive('fetchArray')
            ->andReturn($this->latestScanners[0],$this->latestScanners[1],false);
    $this->dbManagerMock->shouldReceive('freeResult');
    $container = M::mock('ContainerBuilder');
    $container->shouldReceive('get')->withArgs(array('db.manager'))->andReturn($this->dbManagerMock);
  }

  protected function tearDown() : void
  {
    M::close();
  }

  /**
   * @group repairme
   */
  public function testGetScannerDetectedLicenses()
  {
    $uploadId = 2;
    $nomos = $this->latestScanners[0];
    $monk = $this->latestScanners[1];
    list($licenseMatch1, $licenseRef1, $agentRef1) = $this->createLicenseMatch(5, "licA", $nomos['agent_pk'], $nomos['agent_name'], 453, null);
    list($licenseMatch2, $licenseRef2, $agentRef2) = $this->createLicenseMatch(5, "licA", $monk['agent_pk'], $monk['agent_name'], 665, 95);
    list($licenseMatch3, $licenseRef3, $agentRef3) = $this->createLicenseMatch(7, "licB", $monk['agent_pk'], $monk['agent_name'], 545, 97);
    $licenseMatches = array($licenseMatch1, $licenseMatch2, $licenseMatch3);

    $this->itemTreeBounds->shouldReceive('getUploadId')->withNoArgs()->andReturn($uploadId);
    $this->licenseDao->shouldReceive('getAgentFileLicenseMatches')->once()
            ->withArgs(array($this->itemTreeBounds,LicenseMap::TRIVIAL,false))
            ->andReturn($licenseMatches);
    $scannerDetectedLicenses = $this->agentLicenseEventProcessor->getScannerDetectedLicenses($this->itemTreeBounds);

    assertThat($scannerDetectedLicenses, is(array(
        5 => $licenseRef1,
        7 => $licenseRef3
    )));
  }

  public function testGetScannerDetectedLicenseDetails()
  {
    $uploadId = 2;
    $licId = 5;
    $nomos = $this->latestScanners[0];
    $monk = $this->latestScanners[1];
    list($licenseMatch1, $licenseRef1, $agentRef1) = $this->createLicenseMatch($licId, "licA", $nomos['agent_pk'], $nomos['agent_name'], 453, null);
    list($licenseMatch2, $licenseRef2, $agentRef2) = $this->createLicenseMatch($licId, "licA", $monk['agent_pk'], $monk['agent_name'], 665, 95);
    $licenseMatches = array($licenseMatch1, $licenseMatch2);

    $this->itemTreeBounds->shouldReceive('getUploadId')->withNoArgs()->andReturn($uploadId);
    $this->licenseDao->shouldReceive('getAgentFileLicenseMatches')->once()
            ->withArgs(array($this->itemTreeBounds,LicenseMap::TRIVIAL, false))
            ->andReturn($licenseMatches);

    // $latestAgentDetectedLicenses = $this->agentLicenseEventProcessor->getScannerDetectedLicenseDetails($this->itemTreeBounds);
    $reflection = new \ReflectionClass($this->agentLicenseEventProcessor);
    $method = $reflection->getMethod('getScannerDetectedLicenseDetails');
    $method->setAccessible(true);
    $latestAgentDetectedLicenses = $method->invoke($this->agentLicenseEventProcessor,$this->itemTreeBounds);

    assertThat($latestAgentDetectedLicenses, array(
            'nomos' => array(
                array('id' => $licId, 'licenseRef' => $licenseRef1, 'agentRef' => $agentRef1, 'matchId' => 453, 'percentage' => null)
            ),
            'monk' => array(
                array('id' => $licId, 'licenseRef' => $licenseRef2, 'agentRef' => $agentRef2, 'matchId' => 665, 'percentage' => 95)
            )
    ) );
  }

  public function testGetScannerDetectedLicenseDetailsWithUnknownAgent()
  {
    $uploadId = 2;
    list($licenseMatch1, $licenseRef1, $agentRef1) = $this->createLicenseMatch(5, "licA", 23, "nomos", 453, null);
    list($licenseMatch2, $licenseRef2, $agentRef2) = $this->createLicenseMatch(5, "licA", 22, "unknown", 665, 95);
    $licenseMatches = array($licenseMatch1, $licenseMatch2);

    $this->itemTreeBounds->shouldReceive('getUploadId')->withNoArgs()->andReturn($uploadId);
    $this->licenseDao->shouldReceive('getAgentFileLicenseMatches')->once()
            ->withArgs(array($this->itemTreeBounds,LicenseMap::TRIVIAL,false))
            ->andReturn($licenseMatches);

    // $latestAgentDetectedLicenses = $this->agentLicenseEventProcessor->getScannerDetectedLicenseDetails($this->itemTreeBounds);
    $reflection = new \ReflectionClass($this->agentLicenseEventProcessor);
    $method = $reflection->getMethod('getScannerDetectedLicenseDetails');
    $method->setAccessible(true);
    $latestAgentDetectedLicenses = $method->invoke($this->agentLicenseEventProcessor,$this->itemTreeBounds);

    assertThat($latestAgentDetectedLicenses, is(array(
        5 => array(
            'nomos' => array(
                array('id' => 5, 'licenseRef' => $licenseRef1, 'agentRef' => $agentRef1, 'matchId' => 453, 'percentage' => null)
            )
        )
    )));
  }

  public function testGetScannerDetectedLicenseDetailsWithOutdatedMatches()
  {
    $uploadId = 2;
    list($licenseMatch1, $licenseRef1, $agentRef1) = $this->createLicenseMatch(5, "licA", 17, "nomos", 453, null);
    list($licenseMatch2, $licenseRef2, $agentRef2) = $this->createLicenseMatch(5, "licA", 18, "monk", 665, 95);
    $licenseMatches = array($licenseMatch1, $licenseMatch2);

    $this->itemTreeBounds->shouldReceive('getUploadId')->withNoArgs()->andReturn($uploadId);
    $this->licenseDao->shouldReceive('getAgentFileLicenseMatches')->once()
            ->withArgs(array($this->itemTreeBounds,LicenseMap::TRIVIAL,false))
            ->andReturn($licenseMatches);

    // $latestAgentDetectedLicenses = $this->agentLicenseEventProcessor->getScannerDetectedLicenseDetails($this->itemTreeBounds);
    $reflection = new \ReflectionClass($this->agentLicenseEventProcessor);
    $method = $reflection->getMethod('getScannerDetectedLicenseDetails');
    $method->setAccessible(true);
    $latestAgentDetectedLicenses = $method->invoke($this->agentLicenseEventProcessor,$this->itemTreeBounds);

    assertThat($latestAgentDetectedLicenses, is(array()));
  }

  public function testGetScannerDetectedLicenseDetailsNoLicenseFoundShouldBeSkipped()
  {
    $uploadId = 2;
    list($licenseMatch1, $licenseRef1, $agentRef1) = $this->createLicenseMatch(5, "No_license_found", 23, "nomos", 453, null);
    $licenseMatches = array($licenseMatch1);

    $this->itemTreeBounds->shouldReceive('getUploadId')->withNoArgs()->andReturn($uploadId);
    $this->licenseDao->shouldReceive('getAgentFileLicenseMatches')->once()
            ->withArgs(array($this->itemTreeBounds,LicenseMap::TRIVIAL,false))
            ->andReturn($licenseMatches);

    // $latestAgentDetectedLicenses = $this->agentLicenseEventProcessor->getScannerDetectedLicenseDetails($this->itemTreeBounds);
    $reflection = new \ReflectionClass($this->agentLicenseEventProcessor);
    $method = $reflection->getMethod('getScannerDetectedLicenseDetails');
    $method->setAccessible(true);
    $latestAgentDetectedLicenses = $method->invoke($this->agentLicenseEventProcessor,$this->itemTreeBounds);

    assertThat($latestAgentDetectedLicenses, is(array()));
  }

  /**
   * @return M\MockInterface
   */
  protected function createLicenseMatch($licenseId, $licenseShortName, $agentId, $agentName, $matchId, $percentage)
  {
    $licenseRef = M::mock(LicenseRef::class);
    $licenseRef->shouldReceive("getId")->withNoArgs()->andReturn($licenseId);
    $licenseRef->shouldReceive("getShortName")->withNoArgs()->andReturn($licenseShortName);

    $agentRef = M::mock(LicenseRef::class);
    $agentRef->shouldReceive("getAgentId")->withNoArgs()->andReturn($agentId);
    $agentRef->shouldReceive("getAgentName")->withNoArgs()->andReturn($agentName);
    $agentRef->shouldReceive("getAgentName")->withNoArgs()->andReturn($agentName);

    $licenseMatch = M::mock(LicenseMatch::class);
    $licenseMatch->shouldReceive("getLicenseRef")->withNoArgs()->andReturn($licenseRef);
    $licenseMatch->shouldReceive("getAgentRef")->withNoArgs()->andReturn($agentRef);
    $licenseMatch->shouldReceive("getLicenseFileId")->withNoArgs()->andReturn($matchId);
    $licenseMatch->shouldReceive("getPercentage")->withNoArgs()->andReturn($percentage);
    return array($licenseMatch, $licenseRef, $agentRef);
  }

  public function testGetScannedLicenses()
  {
    /** @var LicenseRef $licenseRef1 */
    list($licenseMatch1, $licenseRef1, $agentRef1) = $this->createLicenseMatch(5, "licA", 23, "nomos", 453, null);

    $details = array(
        5 => array(
            'nomos' => array(
                array('id' => 5, 'licenseRef' => $licenseRef1, 'agentRef' => $agentRef1, 'matchId' => 453, 'percentage' => null)
            )
        )
    );

    $result = $this->agentLicenseEventProcessor->getScannedLicenses($details);

    assertThat($result, is(array($licenseRef1->getId() => $licenseRef1)));
  }

  public function testGetScannedLicensesWithEmptyDetails()
  {
    assertThat($this->agentLicenseEventProcessor->getScannedLicenses(array()), is(emptyArray()));
  }
}
