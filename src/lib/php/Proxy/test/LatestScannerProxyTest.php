<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Proxy;

use Exception;
use Fossology\Lib\Test\TestLiteDb;
use Mockery as M;

class LatestScannerProxyTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestLiteDb */
  private $testDb;
  /** @var assertCountBefore */
  private $assertCountBefore;


  protected function setUp() : void
  {
    $this->testDb = new TestLiteDb();
    $this->testDb->createPlainTables( array('agent','ars_master') );
    $dbManager = &$this->testDb->getDbManager();
    $dbManager->queryOnce('ALTER TABLE ars_master RENAME TO nomos_ars');
    $this->testDb->insertData(array('agent','nomos_ars'));
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    M::close();
  }

  private function getAllColumns($sql,$params=array())
  {
    $backtrace = debug_backtrace();
    $caller = $backtrace[1];
    $stmt = "$caller[class]::$caller[function]";

    $dbManager = &$this->testDb->getDbManager();
    $dbManager->prepare($stmt, $sql);

    $res = $dbManager->execute($stmt,$params);
    $result = $dbManager->fetchAll($res);
    $dbManager->freeResult($res);

    return $result;
  }

  public function testQuery()
  {
    $uploadId = 2;
    $agentNames = array('nomos');
    $latestScannerProxy = new LatestScannerProxy($uploadId,$agentNames,'latest_scanner', "AND agent_enabled='true'");
    $sql = $latestScannerProxy->getDbViewQuery();
    $scanners = $this->getAllColumns($sql);
    assertThat($scanners,arrayContaining(array(array('agent_pk'=>6,'agent_name'=>'nomos'))));
  }

  public function testQueryTwoScanners()
  {
    $this->testDb->getDbManager()->queryOnce('CREATE TABLE monk_ars AS SELECT * FROM nomos_ars WHERE 0=1');
    $this->testDb->insertData(array('monk_ars'));
    $uploadId = 2;
    $agentNames = array('nomos','monk');
    $latestScannerProxy = new LatestScannerProxy($uploadId,$agentNames,'latest_scanner', "AND agent_enabled='true'");
    $sql = $latestScannerProxy->getDbViewQuery();
    $scanners = $this->getAllColumns($sql);
    assertThat($scanners,arrayContainingInAnyOrder(array(array('agent_pk'=>6,'agent_name'=>'nomos'),array('agent_pk'=>5,'agent_name'=>'monk'))));
  }

  public function testQueryNoScanners()
  {
    $this->expectException(Exception::class);
    $uploadId = 2;
    $agentNames = array();
    new LatestScannerProxy($uploadId,$agentNames,'latest_scanner', "AND agent_enabled='true'");
  }

  public function testQueryPrepared()
  {
    $uploadId = 2;
    $agentNames = array('nomos');
    $latestScannerProxy = new LatestScannerProxy('$1',$agentNames,'latest_scanner', "AND agent_enabled='true'");
    $sql = $latestScannerProxy->getDbViewQuery();
    $scanners = $this->getAllColumns($sql,array($uploadId));
    assertThat($scanners,arrayContaining(array(array('agent_pk'=>6,'agent_name'=>'nomos'))));
  }

  public function testMaterializePossibleForUnparameterizedQuery()
  {
    $uploadId = 2;
    $agentNames = array('nomos');
    $latestScannerProxy = new LatestScannerProxy($uploadId,$agentNames,'latest_scanner', "AND agent_enabled='true'");
    $latestScannerProxy->materialize();
  }

  public function testMaterializeNotPossibleForParameterizedQuery()
  {
    $this->expectException(Exception::class);
    $agentNames = array('nomos');
    $latestScannerProxy = new LatestScannerProxy('$1',$agentNames,'latest_scanner', "AND agent_enabled='true'");
    $latestScannerProxy->materialize();
  }


  public function testGetNameToIdMap()
  {
    $uploadId = 2;
    $agentNames = array('nomos');
    $latestScannerProxy = new LatestScannerProxy($uploadId,$agentNames,'latest_scanner', "AND agent_enabled='true'");
    $map = $latestScannerProxy->getNameToIdMap();
    assertThat($map,equalTo(array('nomos'=>6)));
  }

  public function testGetNameToIdMapNotPossibleForParameterizedQuery()
  {
    $this->expectException(Exception::class);
    $agentNames = array('nomos');
    $latestScannerProxy = new LatestScannerProxy('$1',$agentNames,'latest_scanner', "AND agent_enabled='true'");
    $latestScannerProxy->getNameToIdMap();
  }
}
