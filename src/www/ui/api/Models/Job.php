<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Job model
 */

namespace Fossology\UI\Api\Models;

use Fossology\UI\Api\Models\ApiVersion;
use Fossology\Lib\Dao\UserDao;

/**
 * @class Job
 * @package Fossology\UI\Api\Models
 * @brief Job model to hold job related info
 */
class Job
{
  /**
   * @var integer $id
   * Job id
   */
  private $id;
  /**
   * @var string $name
   * Job name
   */
  private $name;
  /**
   * @var string $queueDate
   * Job queue date
   */
  private $queueDate;
  /**
   * @var integer $uploadId
   * Upload id for current job
   */
  private $uploadId;
  /**
   * @var integer $userId
   * User id for current job
   */
  private $userId;
  /**
   * @var integer $groupId
   * Group id for current job
   */
  private $groupId;
  /**
   * @var integer $eta
   * Estimated time of completion of job
   */
  private $eta;

  /**
   * @var string $status
   * The status of the job. Can be one of following:\n
   *      - Completed
   *      - Failed
   *      - Queued
   *      - Processing
   */
  private $status;
  /**
   * @var array $jobQueue
   * Array of JobQueues
   */
  private $jobQueue;

  /**
   * Job constructor.
   *
   * @param integer $id
   * @param string $name
   * @param string $queueDate
   * @param integer $uploadId
   * @param integer $userId
   * @param integer $groupId
   * @param integer $eta
   * @param string $status
   * @param array $jobQueue
   */
  public function __construct($id, $name = "", $queueDate = "", $uploadId = 0,
    $userId = 0, $groupId = 0, $eta = 0, $status = "", $jobQueue = [])
  {
    $this->id = intval($id);
    $this->name = $name;
    $this->queueDate = $queueDate;
    $this->uploadId = intval($uploadId);
    $this->userId = intval($userId);
    $this->groupId = intval($groupId);
    $this->eta = intval($eta);
    $this->status = $status;
    $this->jobQueue = $jobQueue;
  }

  /**
   * JSON representation of current job
   * @return string
   */
  public function getJSON($version=ApiVersion::V1)
  {
    return json_encode($this->getArray($version));
  }

  /**
   * Get Job element as associative array
   * @return array
   */
  public function getArray($version = ApiVersion::V1)
  {
    if ($version == ApiVersion::V2) {

      /** @var UserDao */
      $userDao = $GLOBALS['container']->get("dao.user");
      return [
        'id'        => $this->id,
        'name'      => $this->name,
        'queueDate' => $this->queueDate,
        'uploadId'  => $this->uploadId,
        'userName'  => $userDao->getUserName($this->userId),
        'groupName' => $userDao->getGroupNameById($this->groupId),
        'eta'       => $this->eta,
        'status'    => $this->status,
        'jobQueue'  => $this->jobQueue
      ];
    } else {
      return [
        'id'        => $this->id,
        'name'      => $this->name,
        'queueDate' => $this->queueDate,
        'uploadId'  => $this->uploadId,
        'userId'    => $this->userId,
        'groupId'   => $this->groupId,
        'eta'       => $this->eta,
        'status'    => $this->status
      ];
    }
  }

  /**
   * Get the job ID
   * @return number Job id
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * Get the job name
   * @return string Job name
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * Get date with timezone when job was added
   * @return string Job date
   */
  public function getQueueDate()
  {
    return $this->queueDate;
  }

  /**
   * Get upload id
   * @return number Upload id
   */
  public function getUploadId()
  {
    return $this->uploadId;
  }

  /**
   * Get user id
   * @return number User id
   */
  public function getUserId()
  {
    return $this->userId;
  }

  /**
   * Get group id
   * @return number Group id
   */
  public function getGroupId()
  {
    return $this->groupId;
  }

  /**
   * Get job ETA in seconds
   * @return number Job ETA in seconds
   */
  public function getEta()
  {
    return $this->eta;
  }

  /**
   * Get job status
   * @return string Job status
   */
  public function getStatus()
  {
    return $this->status;
  }

  /**
   * Get job queue
   * @return array Job queue
   */
  public function getJobQueue()
  {
    return $this->jobQueue;
  }

  /**
   * Set the job name
   * @param string $name Job name
   */
  public function setName($name)
  {
    $this->name = $name;
  }

  /**
   * Set the job queue date
   * @param string $queueDate New queue date
   */
  public function setQueueDate($queueDate)
  {
    $this->queueDate = $queueDate;
  }

  /**
   * Set the job upload id
   * @param number $uploadId Job upload id
   */
  public function setUploadId($uploadId)
  {
    $this->uploadId = $uploadId;
  }

  /**
   * Set the user id
   * @param number $userId User id
   */
  public function setUserId($userId)
  {
    $this->userId = $userId;
  }

  /**
   * Set the group id
   * @param number $groupId New group id
   */
  public function setGroupId($groupId)
  {
    $this->groupId = $groupId;
  }

  /**
   * Set the job ETA
   * @param number $eta Job ETA
   */
  public function setEta($eta)
  {
    $this->eta = $eta;
  }

  /**
   * Set the job status
   * @param string $status Job status
   */
  public function setStatus($status)
  {
    $this->status = $status;
  }

  /**
   * Set the job queue
   * @param array $jobQueue Job queue
   */
  public function setJobQueue($jobQueue)
  {
    $this->jobQueue = $jobQueue;
  }
}
