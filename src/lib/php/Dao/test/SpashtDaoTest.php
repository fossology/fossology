<?php
/*
 SPDX-FileCopyrightText: © 2024 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Data\Spasht\Coordinate;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;
use Monolog\Logger;

class SpashtDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var SpashtDao */
  private $spashtDao;
  /** @var integer */
  private $assertCountBefore;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = $this->testDb->getDbManager();
    $logger = new Logger("test");

    $this->spashtDao = new SpashtDao($this->dbManager, $logger);

    $this->testDb->createPlainTables(array('spasht', 'spasht_ars'));

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

  public function testAddComponentRevision()
  {
    $coordinate = new Coordinate([
        'type' => 'npm',
        'provider' => 'npmjs',
        'namespace' => null,
        'name' => 'test-pkg',
        'revision' => '1.0.0'
    ]);
    
    $id = $this->spashtDao->addComponentRevision($coordinate, 1);
    assertThat($id, greaterThan(0));
    
    $found = $this->spashtDao->getComponent(1);
    assertThat($found->getName(), is('test-pkg'));
  }

  public function testAlterComponentRevision()
  {
    $coordinate1 = new Coordinate(['type'=>'t','provider'=>'p','namespace'=>'ns','name'=>'n1','revision'=>'v1']);
    $this->spashtDao->addComponentRevision($coordinate1, 1);
    
    $coordinate2 = new Coordinate(['type'=>'t','provider'=>'p','namespace'=>'ns','name'=>'n2','revision'=>'v2']);
    $this->spashtDao->alterComponentRevision($coordinate2, 1);
    
    $found = $this->spashtDao->getComponent(1);
    assertThat($found->getName(), is('n2'));
  }
}
