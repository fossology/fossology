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

namespace Fossology\Lib\Db;

use Mockery as M;
use Mockery\MockInterface;
use Monolog\Logger;

class DbManagerTest extends \PHPUnit_Framework_TestCase
{

  /** @var Driver|MockInterface */
  private $driver;

  /** @var DbManager */
  private $dbManager;

  function setUp()
  {
    $this->driver = M::mock('Fossology\\Lib\\Db\\Driver');
    $logger = new Logger(__FILE__);
    $this->dbManager = new DbManager($logger);
    $this->dbManager->setDriver($this->driver);
  }

  function tearDown()
  {
    M::close();
  }

  function test_begin_transaction()
  {
    $this->driver->shouldReceive("begin")->withNoArgs()->once();
    $this->dbManager->begin();
  }

  function test_commit_transaction()
  {
    $this->driver->shouldReceive("commit")->withNoArgs()->once();
    $this->dbManager->commit();
  }
}
 