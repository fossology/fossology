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

namespace Fossology\Lib\Util;

use Mockery as M;

class HackedTimingLogger extends TimingLogger
{
  public $timestamp = 3.1415926;
  public function getTimestamp()
  {
    return $this->timestamp;
  }
}

class TimingLoggerTest extends \PHPUnit_Framework_TestCase
{
  private $logger;
  
  public function setUp()
  {
    $this->logger = M::mock('Monolog\Logger');
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  public function tearDown() {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    M::close();
  }

  public function testTicToc()
  {
    $hackedTimingLogger = new HackedTimingLogger($this->logger);
    $startTime = 19;
    $endTime = 42;
    $text = 'whatever';
    $expected = sprintf("%s (%.3fms)", $text, ($endTime - $startTime) * 1000);
    $this->logger->shouldReceive('debug')->with($expected);
    $hackedTimingLogger->timestamp = $startTime;
    $hackedTimingLogger->tic();
    $hackedTimingLogger->timestamp = $endTime;
    $hackedTimingLogger->toc($text);
  }

  public function testTicTocOtherWatch()
  {
    $hackedTimingLogger = new HackedTimingLogger($this->logger);
    $startTime = 19;
    $endTime = 42;
    $text = 'whatever';
    $watch = 'otherWatch';
    $expected = sprintf("%s (%.3fms)", $text, ($endTime - $startTime) * 1000);
    $this->logger->shouldReceive('debug')->with($expected);
    $hackedTimingLogger->timestamp = $startTime;
    $hackedTimingLogger->tic($watch);
    $hackedTimingLogger->timestamp = ($startTime+$endTime)/2;
    $hackedTimingLogger->tic('default');
    $hackedTimingLogger->timestamp = $endTime;
    $hackedTimingLogger->toc($text,$watch);
  }
  
}
 