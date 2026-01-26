<?php
/*
 SPDX-FileCopyrightText: © 2026 Contributors to FOSSology

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Mockery as M;
use PHPUnit\Framework\TestCase;

/**
 * @class LicenseClearedGetterTest
 * @brief Tests for LicenseClearedGetter
 */
class LicenseClearedGetterTest extends TestCase
{
  /** @var LicenseClearedGetter */
  private $licenseClearedGetter;

  protected function setUp(): void
  {
    global $container;
    $container = M::mock('ContainerBuilder');

    $this->clearingDao = M::mock(ClearingDao::class);
    $this->licenseDao = M::mock(LicenseDao::class);
    $this->agentDao = M::mock(AgentDao::class);
    $this->uploadDao = M::mock(UploadDao::class);
    $this->dbManager = M::mock(DbManager::class);

    $container->shouldReceive('get')->with('dao.clearing')->andReturn($this->clearingDao);
    $container->shouldReceive('get')->with('dao.license')->andReturn($this->licenseDao);
    $container->shouldReceive('get')->with('dao.agent')->andReturn($this->agentDao);
    $container->shouldReceive('get')->with('dao.upload')->andReturn($this->uploadDao);
    $container->shouldReceive('get')->with('db.manager')->andReturn($this->dbManager);

    $this->licenseClearedGetter = new LicenseClearedGetter();
  }

  protected function tearDown(): void
  {
    M::close();
  }

  /**
   * @brief Test that setExcludeIrrelevant sets the property correctly
   */
  public function testSetExcludeIrrelevant()
  {
    // By default, excludeIrrelevant should be true
    $reflection = new \ReflectionClass($this->licenseClearedGetter);
    $property = $reflection->getProperty('excludeIrrelevant');
    $property->setAccessible(true);

    $this->assertTrue($property->getValue($this->licenseClearedGetter));

    // Set to false
    $this->licenseClearedGetter->setExcludeIrrelevant(false);
    $this->assertFalse($property->getValue($this->licenseClearedGetter));

    // Set back to true
    $this->licenseClearedGetter->setExcludeIrrelevant(true);
    $this->assertTrue($property->getValue($this->licenseClearedGetter));
  }
}
