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

namespace Fossology\Decider;

use Fossology\Lib\Data\LicenseMatch;
use Mockery as M;
use Fossology\Lib\Test\Reflectory;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\BusinessRules\AgentLicenseEventProcessor;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DecisionTypes;


global $container;
$container = M::mock('ContainerBuilder');
$container->shouldReceive('get')->withArgs(array('db.manager'))->andReturn(M::mock(DbManager::classname()));
$agentDao = M::mock(AgentDao::classname());
$agentDao->shouldReceive('getCurrentAgentId')->andReturn(1234);
$container->shouldReceive('get')->withArgs(array('dao.agent'))->andReturn($agentDao);
require_once(__DIR__ . '/../../../lib/php/Test/Agent/AgentTestMockHelper.php');
require_once(__DIR__ . '/../../agent/DeciderAgent.php');


class DeciderAgentTest extends \PHPUnit_Framework_TestCase
{
  private $highlightDao;
  
  protected function setUp()
  {
    global $container;
    $this->highlightDao = M::mock(HighlightDao::classname());
    $container->shouldReceive('get')->withArgs(array('dao.highlight'))->andReturn($this->highlightDao);
    $container->shouldReceive('get')->withArgs(array('dao.upload'))->andReturn(M::mock(UploadDao::classname()));
    $container->shouldReceive('get')->withArgs(array('dao.clearing'))->andReturn(M::mock(ClearingDao::classname()));
    $container->shouldReceive('get')->withArgs(array('decision.types'))->andReturn(M::mock(DecisionTypes::classname()));
    $container->shouldReceive('get')->withArgs(array('businessrules.clearing_decision_processor'))->andReturn(M::mock(ClearingDecisionProcessor::classname()));
    $container->shouldReceive('get')->withArgs(array('businessrules.agent_license_event_processor'))->andReturn(M::mock(AgentLicenseEventProcessor::classname()));
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown()
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    M::close();
  }

  public function testAreNomosMatchesInsideAMonkMatchIfNoneAtAll()
  {
    $deciderAgent = new DeciderAgent();

    $reflection = new \ReflectionClass($deciderAgent); 
    $method = $reflection->getMethod('areNomosMatchesInsideAMonkMatch');
    $method->setAccessible(true);
    
    $licenseMatches = array();
    assertThat( $method->invoke($deciderAgent,$licenseMatches), equalTo(false) );
  }

  public function testAreNomosMatchesInsideAMonkMatchIfNoMonk()
  {
    $deciderAgent = new DeciderAgent();

    $reflection = new \ReflectionClass($deciderAgent); 
    $method = $reflection->getMethod('areNomosMatchesInsideAMonkMatch');
    $method->setAccessible(true);

    $this->highlightDao->shouldReceive('getHighlightRegion')->andReturn(array($start=2, $end=5));
    $licenseMatches = array('nomos'=>
        array($this->createLicenseMatch('nomos',1))
        );
    assertThat( $method->invoke($deciderAgent,$licenseMatches), equalTo(false) );
  }

  public function testAreNomosMatchesInsideAMonkMatchIfNoNomos()
  {
    $deciderAgent = new DeciderAgent();

    $reflection = new \ReflectionClass($deciderAgent); 
    $method = $reflection->getMethod('areNomosMatchesInsideAMonkMatch');
    $method->setAccessible(true);

    $this->highlightDao->shouldReceive('getHighlightRegion')->andReturn(array($start=2, $end=5));
    $licenseMatches = array('monk'=>
        array($this->createLicenseMatch('monk',1))
        );
    assertThat( $method->invoke($deciderAgent,$licenseMatches), equalTo(false) );
  }
  
  public function testAreNomosMatchesInsideAMonkMatchIfNotFit()
  {
    $deciderAgent = new DeciderAgent();

    $reflection = new \ReflectionClass($deciderAgent); 
    $method = $reflection->getMethod('areNomosMatchesInsideAMonkMatch');
    $method->setAccessible(true);
    $monkId = 1;
    $nomosId = 2;
    $this->highlightDao->shouldReceive('getHighlightRegion')->with($monkId)->andReturn(array($start=2, $end=5));
    $this->highlightDao->shouldReceive('getHighlightRegion')->with($nomosId)->andReturn(array($start=2, $end=8));
    $licenseMatches = array('monk'=>array($this->createLicenseMatch('monk',$monkId)),
            'nomos'=>array($this->createLicenseMatch('nomos',$nomosId)));
    assertThat( $method->invoke($deciderAgent,$licenseMatches), equalTo(false) );
  }
  
  public function testAreNomosMatchesInsideAMonkMatchIfFit()
  {
    $deciderAgent = new DeciderAgent();

    $reflection = new \ReflectionClass($deciderAgent); 
    $method = $reflection->getMethod('areNomosMatchesInsideAMonkMatch');
    $method->setAccessible(true);
    $monkId = 1;
    $nomosId = 2;
    $this->highlightDao->shouldReceive('getHighlightRegion')->with($monkId)->andReturn(array($start=2, $end=5));
    $this->highlightDao->shouldReceive('getHighlightRegion')->with($nomosId)->andReturn(array($start=4, $end=5));
    $licenseMatches = array('monk'=>array($this->createLicenseMatch('monk',$monkId)),
            'nomos'=>array($this->createLicenseMatch('nomos',$nomosId)));
    assertThat( $method->invoke($deciderAgent,$licenseMatches), equalTo(true) );
  }

  
  /**
   * @return M\MockInterface
   */
  protected function createLicenseMatch($agentName, $matchId)
  {
    $licenseMatch = M::mock(LicenseMatch::classname());
    $licenseMatch->shouldReceive("getLicenseFileId")->withNoArgs()->andReturn($matchId);
    return $licenseMatch;
  }
  
  public function testAreNomosMonkNinkaAgreed_notIfOnlyTwoOfThem()
  {
    $deciderAgent = new DeciderAgent();
    $licId = 401;
    $licenseMatches = array('monk'=>array($this->createLicenseMatchWithLicId($licId)),
            'nomos'=>array($this->createLicenseMatchWithLicId($licId)));
    $agree = Reflectory::invokeObjectsMethodnameWith($deciderAgent, 'areNomosMonkNinkaAgreed', array($licenseMatches));
    assertThat($agree, equalTo(false) );
  }
  
  public function testAreNomosMonkNinkaAgreed_alsoMultiMatch()
  {
    $deciderAgent = new DeciderAgent();
    $licId = 401;
    $licenseMatches = array('monk'=>array($this->createLicenseMatchWithLicId($licId)),
            'nomos'=>array($this->createLicenseMatchWithLicId($licId),$this->createLicenseMatchWithLicId($licId)),
            'ninka'=>array($this->createLicenseMatchWithLicId($licId)));
    $agree = Reflectory::invokeObjectsMethodnameWith($deciderAgent, 'areNomosMonkNinkaAgreed', array($licenseMatches));
    assertThat($agree, equalTo(true) );
  }

  
  public function testAreNomosMonkNinkaAgreed_notIfAnyOther()
  {
    $deciderAgent = new DeciderAgent();
    $licId = 401;
    $otherLicId = 402;
    $licenseMatches = array('monk'=>array($this->createLicenseMatchWithLicId($licId)),
            'nomos'=>array($this->createLicenseMatchWithLicId($licId),$this->createLicenseMatchWithLicId($otherLicId)),
            'ninka'=>array($this->createLicenseMatchWithLicId($licId)));
    $agree = Reflectory::invokeObjectsMethodnameWith($deciderAgent, 'areNomosMonkNinkaAgreed', array($licenseMatches));
    assertThat($agree, equalTo(false) );
  }
  
  /**
   * @return M\MockInterface
   */
  protected function createLicenseMatchWithLicId($licId)
  {
    $licenseMatch = M::mock(LicenseMatch::classname());
    $licenseMatch->shouldReceive("getLicenseId")->withNoArgs()->andReturn($licId);
    return $licenseMatch;
  }
  
}
 