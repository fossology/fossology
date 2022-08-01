<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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

class TimingLoggerTest extends \PHPUnit\Framework\TestCase
{
  private $logger;

  protected function setUp() : void
  {
    $this->logger = M::mock('Monolog\Logger');
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
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
