<?php
/*
 SPDX-FileCopyrightText: © 2022 Krishna Mahato <krishhtrishh9304@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief JobQueue model
 */
namespace Fossology\UI\Api\Models;

/**
 * @class JobQueue
 * @brief Model class to hold JobQueue info
 */
class JobQueue
{
  /**
   * @var integer $jobQueueId
   * Job queue Id
   */
  private $jobQueueId;
  /**
   * @var string $jobQueueType
   * Job queue type
   */
  private $jobQueueType;
  /**
   * @var string $startTime
   * Start time of the Job
   */
  private $startTime;
  /**
   * @var string $endTime
   * End Time of the Job
   */
  private $endTime;
  /**
   * @var string $status
   * Status of the Job
   */
  private $status;
  /**
   * @var integer $itemsProcessed
   * Total items processed
   */
  private $itemsProcessed;
  /**
   * @var string|null $log
   * Log location
   */
  private $log;
  /**
   * @var array $dependencies
   * Job dependencies
   */
  private $dependencies;
  /**
   * @var float $itemsPerSec
   * Items processed per second
   */
  private $itemsPerSec;
  /**
   * @var boolean $canDoActions
   */
  private $canDoActions;
  /**
   * @var boolean $isInProgress
   * Is the job still in progress
   */
  private $isInProgress;
  /**
   * @var boolean $isReady
   * Is the job ready
   */
  private $isReady;
  /**
   * @var array|null $download
   * Any download related info
   */
  private $download;

  /**
   * Upload constructor.
   * @param integer $jobQueueId
   * @param string $jobQueueType
   * @param string $startTime
   * @param string $endTime
   * @param string $status
   * @param integer $itemsProcessed
   * @param string|null $log
   * @param array $dependencies
   * @param float $itemsPerSec
   * @param boolean $canDoActions
   * @param boolean $isInProgress
   * @param boolean $isReady
   * @param array|null $download
   */
  public function __construct($jobQueueId, $jobQueueType, $startTime, $endTime,
                              $status, $itemsProcessed, $log, $dependencies,
                              $itemsPerSec, $canDoActions, $isInProgress,
                              $isReady, $download)
  {
    $this->setJobQueueId($jobQueueId);
    $this->setJobQueueType($jobQueueType);
    $this->setStartTime($startTime);
    $this->setEndTime($endTime);
    $this->setStatus($status);
    $this->setItemsProcessed($itemsProcessed);
    $this->setLog($log);
    $this->setDependencies($dependencies);
    $this->setItemsPerSec($itemsPerSec);
    $this->setCanDoActions($canDoActions);
    $this->setIsInProgress($isInProgress);
    $this->setIsReady($isReady);
    $this->setDownload($download);
  }

  /**
   * @return int
   */
  public function getJobQueueId()
  {
    return $this->jobQueueId;
  }

  /**
   * @param int $jobQueueId
   */
  public function setJobQueueId($jobQueueId)
  {
    $this->jobQueueId = intval($jobQueueId);
  }

  /**
   * @return string
   */
  public function getJobQueueType()
  {
    return $this->jobQueueType;
  }

  /**
   * @param string $jobQueueType
   */
  public function setJobQueueType($jobQueueType)
  {
    $this->jobQueueType = $jobQueueType;
  }

  /**
   * @return string
   */
  public function getStartTime()
  {
    return $this->startTime;
  }

  /**
   * @param string $startTime
   */
  public function setStartTime($startTime)
  {
    $this->startTime = $startTime;
  }

  /**
   * @return string
   */
  public function getEndTime()
  {
    return $this->endTime;
  }

  /**
   * @param string $endTime
   */
  public function setEndTime($endTime)
  {
    $this->endTime = $endTime;
  }

  /**
   * @return string
   */
  public function getStatus()
  {
    return $this->status;
  }

  /**
   * @param string $status
   */
  public function setStatus($status)
  {
    $this->status = $status;
  }

  /**
   * @return int
   */
  public function getItemsProcessed()
  {
    return $this->itemsProcessed;
  }

  /**
   * @param int $itemsProcessed
   */
  public function setItemsProcessed($itemsProcessed)
  {
    $this->itemsProcessed = intval($itemsProcessed);
  }

  /**
   * @return string|null
   */
  public function getLog()
  {
    return $this->log;
  }

  /**
   * @param string|null $log
   */
  public function setLog($log)
  {
    $this->log = $log;
  }

  /**
   * @return array
   */
  public function getDependencies()
  {
    return $this->dependencies;
  }

  /**
   * @param array $dependencies
   */
  public function setDependencies($dependencies)
  {
    $this->dependencies = [];
    foreach ($dependencies as $dependency) {
      $this->dependencies[] = intval($dependency);
    }
  }

  /**
   * @return float
   */
  public function getItemsPerSec()
  {
    return $this->itemsPerSec;
  }

  /**
   * @param float $itemsPerSec
   */
  public function setItemsPerSec($itemsPerSec)
  {
    $this->itemsPerSec = floatval($itemsPerSec);
  }

  /**
   * @return bool
   */
  public function isCanDoActions()
  {
    return $this->canDoActions;
  }

  /**
   * @param bool $canDoActions
   */
  public function setCanDoActions($canDoActions)
  {
    $this->canDoActions = $canDoActions;
  }

  /**
   * @return bool
   */
  public function isInProgress()
  {
    return $this->isInProgress;
  }

  /**
   * @param bool $isInProgress
   */
  public function setIsInProgress($isInProgress)
  {
    $this->isInProgress = $isInProgress;
  }

  /**
   * @return bool
   */
  public function isReady()
  {
    return $this->isReady;
  }

  /**
   * @param bool $isReady
   */
  public function setIsReady($isReady)
  {
    $this->isReady = $isReady;
  }

  /**
   * @return array|null
   */
  public function getDownload()
  {
    return $this->download;
  }

  /**
   * @param array|null $download
   */
  public function setDownload($download)
  {
    $this->download = $download;
  }

  /**
   * Get JobQueue in JSON representation
   * @return string
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  /**
   * Get the JobQueue as an associative array
   * @return array
   */
  public function getArray()
  {
    return [
      "jobQueueId" => $this->getJobQueueId(),
      "jobQueueType" => $this->getJobQueueType(),
      "startTime" => $this->getStartTime(),
      "endTime" => $this->getEndTime(),
      "status" => $this->getStatus(),
      "itemsProcessed" => $this->getItemsProcessed(),
      "log" => $this->getLog(),
      "dependencies" => $this->getDependencies(),
      "itemsPerSec" => $this->getItemsPerSec(),
      "canDoActions" => $this->isCanDoActions(),
      "isInProgress" => $this->isInProgress(),
      "isReady" => $this->isReady(),
      "download" => $this->getDownload() == null ? null : [
        "text" => $this->getDownload()["text"],
        "link" => $this->getDownload()["link"]
      ]
    ];
  }
}
