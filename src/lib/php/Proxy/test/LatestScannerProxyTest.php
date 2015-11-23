<?php
/*
Copyright (C) 2015, Siemens AG

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

namespace Fossology\Lib\Proxy;

use Fossology\Lib\Test\TestLiteDb;
use Mockery as M;


class LatestScannerProxyTest extends \PHPUnit_Framework_TestCase
{
  /** @var TestLiteDb */
  private $testDb;
  
  protected function setUp()
  {
    $this->testDb = new TestLiteDb();
    $this->testDb->createPlainTables( array('agent','ars_master') );
    $dbManager = &$this->testDb->getDbManager();
    $dbManager->queryOnce('ALTER TABLE ars_master RENAME TO nomos_ars');
    $this->testDb->insertData(array('agent','nomos_ars'));
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown()
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
  
  /**
   * @expectedException \Exception
   */
  public function testQueryNoScanners()
  {
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
  
  /**
   * @expectedException \Exception
   */
  public function testMaterializeNotPossibleForParameterizedQuery()
  {
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
  
  /**
   * @expectedException \Exception
   */
  public function testGetNameToIdMapNotPossibleForParameterizedQuery()
  {
    $agentNames = array('nomos');
    $latestScannerProxy = new LatestScannerProxy('$1',$agentNames,'latest_scanner', "AND agent_enabled='true'");
    $latestScannerProxy->getNameToIdMap();
  }
  
}