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
   * @var boolean $download
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
   * @param array $dependencies
   * @param float $itemsPerSec
   * @param boolean $canDoActions
   * @param boolean $isInProgress
   * @param boolean $isReady
   * @param string $download
   */
  public function __construct($jobQueueId, $jobQueueType, $startTime, $endTime, $status, $itemsProcessed, $dependencies, $itemsPerSec, $canDoActions, $isInProgress, $isReady, $download)
  {
    $this->jobQueueId = intval($jobQueueId);
    $this->jobQueueType = $jobQueueType;
    $this->startTime = $startTime;
    $this->endTime = $endTime;
    $this->status = $status;
    $this->itemsProcessed = intval($itemsProcessed);
    $this->dependencies = $dependencies;
    $this->itemsPerSec = floatval($itemsPerSec);
    $this->canDoActions = $canDoActions;
    $this->isInProgress = $isInProgress;
    $this->isReady = $isReady;
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
        "jobQueueId" => $this->jobQueueId,
        "jobQueueType" => $this->jobQueueType,
        "startTime" => $this->startTime,
        "endTime" => $this->endTime,
        "status" => $this->status,
        "itemsProcessed" => $this->itemsProcessed,
        "dependencies" => $this->dependencies,
        "itemsPerSec" => $this->itemsPerSec,
        "canDoActions" => $this->canDoActions,
        "isInProgress" => $this->isInProgress,
        "isReady" => $this->isReady,
        "download" => $this->download
    ];
  }
}
