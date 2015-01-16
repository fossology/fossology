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

use Monolog\Logger;

class TimingLogger extends Object
{
  const DEFAULT_WATCH = 'default';

  /** @var Logger */
  private $logger;
  
  /** @var float[] */
  private $watchTimes;

  private $startTime;

  public function __construct(Logger $logger)
  {
    $this->logger = $logger;

    $this->startTime = $this->getTimestamp();
    $this->watchTimes = array(self::DEFAULT_WATCH => $this->startTime);
  }

  /**
   * @brief start stopwatch timer
   * 
   * @param string $watch
   */
  public function tic($watch = self::DEFAULT_WATCH)
  {
    $this->watchTimes[$watch] = $this->getTimestamp();
  }
 
  /**
   * @param string $text
   * @param string $watch
   */
  public function toc($text, $watch = self::DEFAULT_WATCH)
  {
    if (!array_key_exists($watch, $this->watchTimes))
    {
      $watch = self::DEFAULT_WATCH;
      $text .= " using watch '$watch'";
    } else if (empty($text))
    {
      $text = "Using watch '$watch'";
    }
    $this->logWithStartAndEndTime($text, $this->watchTimes[$watch], $this->getTimestamp());
  }

  /**
   * @param string $text
   * @param float $startTime
   */
  public function logWithStartTime($text, $startTime)
  {
    $endTime = $this->getTimestamp();
    $this->logWithStartAndEndTime($text, $startTime, $endTime);
  }

  /**
   * @param string $text
   * @param float $startTime
   * @param float $endTime
   */
  public function logWithStartAndEndTime($text, $startTime, $endTime)
  {
    $this->logger->debug(sprintf("%s (%.3fms)", $text, ($endTime - $startTime) * 1000));
    $this->startTime = $endTime;
  }

  protected function getTimestamp()
  {
    return microtime(true);
  }
}