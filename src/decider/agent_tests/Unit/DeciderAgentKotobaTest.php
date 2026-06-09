<?php
/*
 SPDX-FileCopyrightText: © 2025 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Decider;

use Fossology\Lib\BusinessRules\AgentLicenseEventProcessor;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\CompatibilityDao;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\ShowJobsDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\Clearing\ClearingLicense;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseMatch;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\Reflectory;
use Mockery as M;

global $container;
require_once(__DIR__ . '/../../../lib/php/Test/Agent/AgentTestMockHelper.php');
require_once(__DIR__ . '/../../agent/DeciderAgent.php');

/**
 * @class DeciderAgentKotobaTest
 * @brief Unit tests for DeciderAgent::autodecideIfKotobaMatchesNoContradiction()
 */
class DeciderAgentKotobaTest extends \PHPUnit\Framework\TestCase
{
  /** @var DeciderAgent */
  private $deciderAgent;
  /** @var ClearingDecisionProcessor|M\MockInterface */
  private $clearingDecisionProcessor;
  /** @var LicenseMap|M\MockInterface */
  private $licenseMap;
  private $assertCountBefore;

  protected function setUp(): void
  {
    global $container;
    $container = M::mock('ContainerBuilder');

    $dbManager = M::mock(DbManager::class);
    $agentDao  = M::mock(AgentDao::class);
    $agentDao->shouldReceive('getCurrentAgentId')->andReturn(1);

    $this->clearingDecisionProcessor = M::mock(ClearingDecisionProcessor::class);
    $agentLicenseEventProcessor      = M::mock(AgentLicenseEventProcessor::class);
    $clearingDao    = M::mock(ClearingDao::class);
    $uploadDao      = M::mock(UploadDao::class);
    $highlightDao   = M::mock(HighlightDao::class);
    $showJobsDao    = new ShowJobsDao($dbManager, $uploadDao);
    $copyrightDao   = M::mock(CopyrightDao::class);
    $compatibilityDao = M::mock(CompatibilityDao::class);
    $licenseDao     = M::mock(LicenseDao::class);

    $container->shouldReceive('get')->with('db.manager')->andReturn($dbManager);
    $container->shouldReceive('get')->with('dao.agent')->andReturn($agentDao);
    $container->shouldReceive('get')->with('dao.highlight')->andReturn($highlightDao);
    $container->shouldReceive('get')->with('dao.show_jobs')->andReturn($showJobsDao);
    $container->shouldReceive('get')->with('dao.copyright')->andReturn($copyrightDao);
    $container->shouldReceive('get')->with('dao.upload')->andReturn($uploadDao);
    $container->shouldReceive('get')->with('dao.clearing')->andReturn($clearingDao);
    $container->shouldReceive('get')->with('dao.compatibility')->andReturn($compatibilityDao);
    $container->shouldReceive('get')->with('dao.license')->andReturn($licenseDao);
    $container->shouldReceive('get')->with('decision.types')->andReturn(M::mock(DecisionTypes::class));
    $container->shouldReceive('get')->with('businessrules.clearing_decision_processor')
              ->andReturn($this->clearingDecisionProcessor);
    $container->shouldReceive('get')->with('businessrules.agent_license_event_processor')
              ->andReturn($agentLicenseEventProcessor);

    $this->deciderAgent = new DeciderAgent();

    // Inject a LicenseMap mock that acts as identity (no projections)
    $this->licenseMap = M::mock(LicenseMap::class);
    $this->licenseMap->shouldReceive('getProjectedId')->andReturnUsing(function ($id) {
      return $id;
    });
    Reflectory::setObjectsProperty($this->deciderAgent, 'licenseMap', $this->licenseMap);

    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown(): void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    M::close();
  }

  // ------------------------------------------------------------------ helpers

  /**
   * Build a real ClearingEvent with the given license id and event type.
   */
  private function makeClearingEvent(int $licenseId, int $eventType, bool $removed = false): ClearingEvent
  {
    $licenseRef      = new LicenseRef($licenseId, "Lic$licenseId", "License $licenseId", "Lic$licenseId");
    $clearingLicense = new ClearingLicense($licenseRef, $removed, $eventType);
    return new ClearingEvent(
      $licenseId,         // eventId (reuse licenseId for simplicity)
      1,                  // uploadTreeId
      time(),
      1,                  // userId
      1,                  // groupId
      $eventType,
      $clearingLicense
    );
  }

  /**
   * Build a minimal LicenseMatch with getLicenseId() returning $licenseId.
   */
  private function makeScannerMatch(int $licenseId): LicenseMatch
  {
    return new LicenseMatch(
      1,
      new LicenseRef($licenseId, "Lic$licenseId", "License $licenseId", "Lic$licenseId"),
      M::mock(AgentRef::class),
      1
    );
  }

  private function makeItemTreeBounds(): ItemTreeBounds
  {
    return new ItemTreeBounds(10, 'uploadtree', '2', 1, 4);
  }

  // ------------------------------------------------------------------ tests

  /**
   * @test
   * No clearing events → nothing to conclude → returns false.
   */
  public function testReturnsFalseWhenNoCurrentEvents(): void
  {
    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->deciderAgent,
      'autodecideIfKotobaMatchesNoContradiction',
      [$this->makeItemTreeBounds(), [], []]
    );
    $this->assertFalse($result);
  }

  /**
   * @test
   * A single human (USER) event is present → must not auto-conclude → false.
   */
  public function testReturnsFalseWhenHumanEventPresent(): void
  {
    $events = [100 => $this->makeClearingEvent(100, ClearingEventTypes::USER)];

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->deciderAgent,
      'autodecideIfKotobaMatchesNoContradiction',
      [$this->makeItemTreeBounds(), [], $events]
    );
    $this->assertFalse($result);
  }

  /**
   * @test
   * A BULK event is present → must not auto-conclude → false.
   */
  public function testReturnsFalseWhenBulkEventPresent(): void
  {
    $events = [100 => $this->makeClearingEvent(100, ClearingEventTypes::BULK)];

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->deciderAgent,
      'autodecideIfKotobaMatchesNoContradiction',
      [$this->makeItemTreeBounds(), [], $events]
    );
    $this->assertFalse($result);
  }

  /**
   * @test
   * User case 1: scanner={MIT=1, GPL=2}; kotoba adds MIT(1), removes GPL(2).
   * Every scanner license is accounted for → concludes → returns true.
   */
  public function testConcludesWhenAllScannerLicensesAccountedFor(): void
  {
    $mitId = 1;
    $gplId = 2;

    $events = [
      $mitId => $this->makeClearingEvent($mitId, ClearingEventTypes::KOTOBA, false), // add MIT
      $gplId => $this->makeClearingEvent($gplId, ClearingEventTypes::KOTOBA, true),  // remove GPL
    ];

    $scannerMatches = [
      $mitId => ['nomos' => [$this->makeScannerMatch($mitId)]],
      $gplId => ['nomos' => [$this->makeScannerMatch($gplId)]],
    ];

    $itemTreeBounds = $this->makeItemTreeBounds(); // single instance reused below

    $this->clearingDecisionProcessor->shouldReceive('makeDecisionFromLastEvents')
      ->once()
      ->with($itemTreeBounds, M::any(), M::any(), DecisionTypes::IDENTIFIED, false);

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->deciderAgent,
      'autodecideIfKotobaMatchesNoContradiction',
      [$itemTreeBounds, $scannerMatches, $events]
    );
    $this->assertTrue($result);
  }

  /**
   * @test
   * User case 2: scanner={MIT=1, GPL=2, MPL=3}; kotoba adds MIT(1), removes GPL(2).
   * MPL(3) is NOT accounted for → contradiction → returns false, no decision made.
   */
  public function testReturnsFalseWhenScannerFindsUnaccountedLicense(): void
  {
    $mitId = 1;
    $gplId = 2;
    $mplId = 3;

    $events = [
      $mitId => $this->makeClearingEvent($mitId, ClearingEventTypes::KOTOBA, false),
      $gplId => $this->makeClearingEvent($gplId, ClearingEventTypes::KOTOBA, true),
    ];

    $scannerMatches = [
      $mitId => ['nomos' => [$this->makeScannerMatch($mitId)]],
      $gplId => ['nomos' => [$this->makeScannerMatch($gplId)]],
      $mplId => ['nomos' => [$this->makeScannerMatch($mplId)]],  // unaccounted
    ];

    // makeDecisionFromLastEvents must NOT be called
    $this->clearingDecisionProcessor->shouldNotReceive('makeDecisionFromLastEvents');

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->deciderAgent,
      'autodecideIfKotobaMatchesNoContradiction',
      [$this->makeItemTreeBounds(), $scannerMatches, $events]
    );
    $this->assertFalse($result);
  }

  /**
   * @test
   * Kotoba events only, no scanner findings at all → no contradiction → concludes.
   */
  public function testConcludesWhenNoScannerFindings(): void
  {
    $mitId = 1;
    $events = [
      $mitId => $this->makeClearingEvent($mitId, ClearingEventTypes::KOTOBA, false),
    ];

    $this->clearingDecisionProcessor->shouldReceive('makeDecisionFromLastEvents')->once();

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->deciderAgent,
      'autodecideIfKotobaMatchesNoContradiction',
      [$this->makeItemTreeBounds(), [], $events]
    );
    $this->assertTrue($result);
  }

  /**
   * @test
   * Mixed events: one kotoba + one human event → bail immediately, false.
   */
  public function testReturnsFalseWhenMixedKotobaAndHumanEvents(): void
  {
    $events = [
      1 => $this->makeClearingEvent(1, ClearingEventTypes::KOTOBA),
      2 => $this->makeClearingEvent(2, ClearingEventTypes::USER),
    ];

    $this->clearingDecisionProcessor->shouldNotReceive('makeDecisionFromLastEvents');

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->deciderAgent,
      'autodecideIfKotobaMatchesNoContradiction',
      [$this->makeItemTreeBounds(), [], $events]
    );
    $this->assertFalse($result);
  }

  /**
   * @test
   * ClearingDecisionProcessor throws an exception → method returns false gracefully.
   */
  public function testReturnsFalseWhenDecisionProcessorThrows(): void
  {
    $mitId = 1;
    $events = [
      $mitId => $this->makeClearingEvent($mitId, ClearingEventTypes::KOTOBA, false),
    ];

    $this->clearingDecisionProcessor->shouldReceive('makeDecisionFromLastEvents')
      ->once()
      ->andThrow(new \Exception("candidate license"));

    $result = Reflectory::invokeObjectsMethodnameWith(
      $this->deciderAgent,
      'autodecideIfKotobaMatchesNoContradiction',
      [$this->makeItemTreeBounds(), [], $events]
    );
    $this->assertFalse($result);
  }
}
