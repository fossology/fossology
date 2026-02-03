<?php
/*
 SPDX-FileCopyrightText: © 2024 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;
use Monolog\Logger;

class PackageDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var PackageDao */
  private $packageDao;
  /** @var integer */
  private $assertCountBefore;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = $this->testDb->getDbManager();
    $logger = new Logger("test");

    $this->packageDao = new PackageDao($this->dbManager, $logger);

    $this->testDb->createPlainTables(array('package', 'upload_packages', 'upload'));

    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
    M::close();
  }

  public function testCreatePackage()
  {
    $package = $this->packageDao->createPackage('test-package');
    assertThat($package->getName(), is('test-package'));
    assertThat($package->getId(), greaterThan(0));
  }

  public function testAddUploadToPackage()
  {
    $package = $this->packageDao->createPackage('pkg1');
    $this->dbManager->insertTableRow('upload', array('upload_pk' => 1, 'upload_filename' => 'file1'));
    
    $this->packageDao->addUploadToPackage(1, $package);
    
    $found = $this->packageDao->findPackageForUpload(1);
    assertThat($found->getName(), is('pkg1'));
  }
}
