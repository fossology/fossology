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

use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Data\AgentRef;
use Mockery as M;


class ScanJobProxyTest extends \PHPUnit_Framework_TestCase
{
  private $agentDaoMock;
  private $uploadId = 23;
  private $agentId = 601;
  private $agentName = 'scanMe';
  /** @var ScanJobProxy */
  private $scanJobProxy;

  public function setUp()
  {
    $this->agentDaoMock = M::mock(AgentDao::classname());
    $this->scanJobProxy = new ScanJobProxy($this->agentDaoMock,$this->uploadId);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  public function tearDown()
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    M::close();
  }

  private function prepareScanAgentStatus()
  {
    $reflection = new \ReflectionClass($this->scanJobProxy->classname() );
    $method = $reflection->getMethod('scanAgentStatus');
    $method->setAccessible(true);
    
    return $method;
  }
  
  public function testScanAgentStatus()
  {
    $method = $this->prepareScanAgentStatus();
    $successfulAgents = array(array('agent_id'=>$this->agentId,'agent_rev'=>'a0815','agent_name'=>$this->agentName));
    $this->agentDaoMock->shouldReceive('getSuccessfulAgentEntries')->with($this->agentName, $this->uploadId)
            ->andReturn($successfulAgents);
    $this->agentDaoMock->shouldReceive('getRunningAgentIds')->never();
    $this->agentDaoMock->shouldReceive('getCurrentAgentRef')->with($this->agentName)
            ->andReturn(new AgentRef($this->agentId, $this->agentName, 'a0815'));
    
    $vars = $method->invoke($this->scanJobProxy,$this->agentName);
    assertThat($vars, is(array(
      'successfulAgents'=>$successfulAgents,
      'uploadId'=>$this->uploadId,
      'agentName'=>$this->agentName,
      'currentAgentId'=>$this->agentId,
      'currentAgentRev'=>'a0815'
    )));
  }
    
    
  public function testScanAgentStatusLatestStillRuns()
  {
    $method = $this->prepareScanAgentStatus();
    $successfulAgents = array(array('agent_id'=>$this->agentId,'agent_rev'=>'a0815','agent_name'=>$this->agentName));
    $this->agentDaoMock->shouldReceive('getSuccessfulAgentEntries')->with($this->agentName, $this->uploadId)
            ->andReturn($successfulAgents);
    $runningAgentId = $this->agentId+1;
    $this->agentDaoMock->shouldReceive('getRunningAgentIds')->with($this->uploadId, $this->agentName)
            ->andReturn(array($runningAgentId))->once();
    $this->agentDaoMock->shouldReceive('getCurrentAgentRef')->with($this->agentName)
            ->andReturn(new AgentRef($runningAgentId, $this->agentName, 'b1234'));
    
    $vars = $method->invoke($this->scanJobProxy,$this->agentName);
    assertThat($vars, is(array(
      'successfulAgents'=>$successfulAgents,
      'uploadId'=>$this->uploadId,
      'agentName'=>$this->agentName,
      'isAgentRunning'=>true,
      'currentAgentId'=>$runningAgentId,
      'currentAgentRev'=>'b1234'
    )));
  }
  
  
  public function testScanAgentStatusLatestNotRuns()
  {
    $method = $this->prepareScanAgentStatus();
    $successfulAgents = array(array('agent_id'=>$this->agentId,'agent_rev'=>'a0815','agent_name'=>$this->agentName));
    $this->agentDaoMock->shouldReceive('getSuccessfulAgentEntries')->with($this->agentName, $this->uploadId)
            ->andReturn($successfulAgents);
    $runningAgentId = $this->agentId+1;
    $this->agentDaoMock->shouldReceive('getRunningAgentIds')->with($this->uploadId, $this->agentName)
            ->andReturn(array())->once();
    $this->agentDaoMock->shouldReceive('getCurrentAgentRef')->with($this->agentName)
            ->andReturn(new AgentRef($runningAgentId, $this->agentName, 'b1234'));
    
    $vars = $method->invoke($this->scanJobProxy,$this->agentName);
    assertThat($vars, is(array(
      'successfulAgents'=>$successfulAgents,
      'uploadId'=>$this->uploadId,
      'agentName'=>$this->agentName,
      'isAgentRunning'=>false,
      'currentAgentId'=>$runningAgentId,
      'currentAgentRev'=>'b1234'
    )));
  }
  
  
  public function testScanAgentStatusWithoutSuccess()
  {
    $method = $this->prepareScanAgentStatus();
    $successfulAgents = array();
    $this->agentDaoMock->shouldReceive('getSuccessfulAgentEntries')->with($this->agentName, $this->uploadId)
            ->andReturn($successfulAgents);
    $runningAgentId = $this->agentId+1;
    $this->agentDaoMock->shouldReceive('getRunningAgentIds')->with($this->uploadId, $this->agentName)
            ->andReturn(array($runningAgentId))->once();
    $this->agentDaoMock->shouldReceive('getCurrentAgentRef')->with($this->agentName)
            ->andReturn(new AgentRef($runningAgentId, $this->agentName, 'b1234'));
    
    $vars = $method->invoke($this->scanJobProxy,$this->agentName);
    assertThat($vars, is(array(
      'successfulAgents'=>$successfulAgents,
      'uploadId'=>$this->uploadId,
      'agentName'=>$this->agentName,
      'isAgentRunning'=>true
    )));
  }
  
  
  public function testCreateAgentStatus()
  {
    $successfulAgents = array(array('agent_id'=>$this->agentId,'agent_rev'=>'a0815','agent_name'=>$this->agentName));
    $this->agentDaoMock->shouldReceive('getSuccessfulAgentEntries')->with($this->agentName, $this->uploadId)
            ->andReturn($successfulAgents);
    $this->agentDaoMock->shouldReceive('getRunningAgentIds')->never();
    $this->agentDaoMock->shouldReceive('getCurrentAgentRef')->with($this->agentName)
            ->andReturn(new AgentRef($this->agentId, $this->agentName, 'a0815'));
    $fakedAgentName = 'ghost';
    $this->agentDaoMock->shouldReceive('arsTableExists')->with(M::anyOf($this->agentName,$fakedAgentName))->andReturn(true,false)->twice();
    
    $vars = $this->scanJobProxy->createAgentStatus(array($this->agentName,$fakedAgentName));
    assertThat($vars, is(array(array(
      'successfulAgents'=>$successfulAgents,
      'uploadId'=>$this->uploadId,
      'agentName'=>$this->agentName,
      'currentAgentId'=>$this->agentId,
      'currentAgentRev'=>'a0815'
    ))));
  }
  
  
  private function pretendScanAgentStatus($successfulAgents)
  {
    $reflection = new \ReflectionObject($this->scanJobProxy);
    $prop = $reflection->getProperty('successfulScanners');
    $prop->setAccessible(true);
    $prop->setValue($this->scanJobProxy, $successfulAgents);
  }
  
  public function testGetAgentMap()
  {
    $successfulAgents = array($this->agentName=>array(new AgentRef($this->agentId, $this->agentName, 'a0815')));
    $this->pretendScanAgentStatus($successfulAgents);
    
    $expected = array($this->agentId=>"$this->agentName a0815");
    $map = $this->scanJobProxy->getAgentMap();
    assertThat($map,is(equalTo($expected)));
  }
  
  
  public function testGetLatestSuccessfulAgentIds()
  {
    $otherAgentName = 'drinkMe';
    $otherAgentId = 603;
    $successfulAgents = array($this->agentName=>array(new AgentRef($this->agentId, $this->agentName, 'a0815'),
            new AgentRef($this->agentId-1, $this->agentName, 'beforeA0815')),
        $otherAgentName=>array(new AgentRef($otherAgentId, $otherAgentName, 'coffee')));
    $this->pretendScanAgentStatus($successfulAgents);
    
    $expected = array($this->agentName=>$this->agentId,$otherAgentName=>$otherAgentId);
    $ids = $this->scanJobProxy->getLatestSuccessfulAgentIds();
    assertThat($ids,is(equalTo($expected)));
  }
  
  public function testGetSuccessfulAgents()
  {
    $otherAgentName = 'drinkMe';
    $otherAgentId = 603;
    $successfulAgents = array($this->agentName=>array(new AgentRef($this->agentId, $this->agentName, 'a0815'),
            new AgentRef($this->agentId-1, $this->agentName, 'beforeA0815')),
        $otherAgentName=>array(new AgentRef($otherAgentId, $otherAgentName, 'coffee')));
    $this->pretendScanAgentStatus($successfulAgents);
    
    $expected = array_merge($successfulAgents[$this->agentName],$successfulAgents[$otherAgentName]);
    $ids = $this->scanJobProxy->getSuccessfulAgents();
    assertThat($ids,is(equalTo($expected)));
  }
  
}