<?php
/*
 SPDX-FileCopyrightText: © 2022 Krishna Mahato <krishhtrishh9304@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief ShowJob model
 */
namespace Fossology\UI\Api\Models;

/**
 * @class ShowJob
 * @brief Model class to hold ShowJob info
 */
class ShowJob
{
  /**
   * @var integer $jobId
   * Job Id
   */
  private $jobId;
  /**
   * @var string $jobName
   * Name of the Job
   */
  private $jobName;
  /**
   * @var array $jobQueue
   * Array of JobQueues
   */
  private $jobQueue;
  /**
   * @var int $uploadId
   * Upload ID for the job
   */
  private $uploadId;

  /**
   * ShowJob constructor.
   * @param integer $jobId
   * @param string $jobName
   * @param array $jobQueue
   * @param integer $uploadId
   */
  public function __construct($jobId, $jobName, $jobQueue, $uploadId)
  {
    $this->setJobId($jobId);
    $this->setJobName($jobName);
    $this->setJobQueue($jobQueue);
    $this->setUploadId($uploadId);
  }

  /**
   * @return int
   */
  public function getJobId()
  {
    return $this->jobId;
  }

  /**
   * @param int $jobId
   */
  public function setJobId($jobId)
  {
    $this->jobId = intval($jobId);
  }

  /**
   * @return string
   */
  public function getJobName()
  {
    return $this->jobName;
  }

  /**
   * @param string $jobName
   */
  public function setJobName($jobName)
  {
    $this->jobName = $jobName;
  }

  /**
   * @return array
   */
  public function getJobQueue(): array
  {
    return $this->jobQueue;
  }

  /**
   * @param array $jobQueue
   */
  public function setJobQueue($jobQueue)
  {
    $this->jobQueue = $jobQueue;
  }

  /**
   * @return int
   */
  public function getUploadId()
  {
    return $this->uploadId;
  }

  /**
   * @param int $uploadId
   */
  public function setUploadId($uploadId)
  {
    $this->uploadId = intval($uploadId);
  }

  /**
   * Get ShowJob in JSON representation
   * @return string
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  /**
   * Get the ShowJob as an associative array
   * @return array
   */
  public function getArray()
  {
    return [
      "jobId" => $this->getJobId(),
      "jobName" => $this->getJobName(),
      "jobQueue" => $this->getJobQueue(),
      "uploadId" => $this->getUploadId()
    ];
  }
}
