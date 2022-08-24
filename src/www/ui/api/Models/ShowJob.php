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
   * @var array $upload
   * Upload Information
   */
  private $upload;

  /**
   * ShowJob constructor.
   * @param integer $jobId
   * @param string $jobName
   * @param array $jobQueue
   * @param string $upload
   */
  public function __construct($jobId, $jobName, $jobQueue, $upload)
  {
    $this->jobId = intval($jobId);
    $this->jobName = $jobName;
    $this->jobQueue = $jobQueue;
    $this->upload = $upload;
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
        "jobId" => $this->jobId,
        "jobName" => $this->jobName,
        "jobQueue" => $this->jobQueue,
        "upload" => $this->upload
    ];
  }
}
