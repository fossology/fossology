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

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Mockery as M;
use Monolog\Logger;

class AgentsDaoTest extends \PHPUnit_Framework_TestCase {

  private $agentName = "<agentName>";

  /** @var DbManager|M\MockInterface */
  private $dbManager;

  /** @var Logger|M\MockInterface */
  private $logger;

  /** @var AgentsDao */
  private $agentsDao;

  public function setUp() {
    $this->dbManager = M::mock(DbManager::classname());
    $this->logger = M::mock('Monolog\Logger');

    $this->agentsDao = new AgentsDao($this->dbManager, $this->logger);
  }

  public function tearDown() {
    M::close();
  }

  public function testGetNewestAgent()
  {
    $result = array(5, '<rev>');
    $this->dbManager->shouldReceive('getSingleRow')->with("SELECT agent_pk,agent_rev from agent WHERE agent_enabled AND agent_name=$1 "
        . "ORDER BY agent_pk DESC LIMIT 1", array($this->agentName))->once()->andReturn($result);

    assertThat($this->agentsDao->getNewestAgent($this->agentName), is($result));
  }
}
 