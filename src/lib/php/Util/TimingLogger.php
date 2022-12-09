<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/


namespace Fossology\Lib\Util;

use Monolog\Logger;

class TimingLogger
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
    if (! array_key_exists($watch, $this->watchTimes)) {
      $watch = self::DEFAULT_WATCH;
      $text .= " using watch '$watch'";
    } else if (empty($text)) {
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
