<?php
/***************************************************************
Copyright (C) 2021 Robert Bosch GmbH, Dineshkumar Devarajan <Devarajan.Dineshkumar@in.bosch.com>

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
 ***************************************************************/
/**
 * @file
 * @brief JobQueue model
 */

namespace Fossology\UI\Api\Models;

/**
 * @class JobQueue
 * @package Fossology\UI\Api\Models
 * @brief JobQueue model to hold jobqueue related info
 */
class JobQueue
{
  /**
   * @var integer $id
   * JobQueue id
   */
  private $id;
  /**
   * @var string $agent
   * Name of the Agent
   */
  private $agent;
  /**
   * @var string $status
   * The status of the job queue. Can be one of following:\n
   *      - Completed
   *      - Failed
   *      - Queued
   *      - Processing
   *      - Killed by User
   */
  private $status;
  /**
   * @var string $startTime, $endTime
   * Start and End time of the job
   */
  private $startTime, $endTime;
  /**
   * @var integer $itemsProcessed
   * Number of items Processed.
   */
  private $itemsProcessed;

  /**
   * Job constructor.
   *
   * @param integer $id
   * @param string $agent
   * @param string $status
   * @param string $startTime
   * @param string $endTime
   * @param integer $itemsProcessed
   */
  public function __construct($id, $agent = "", $status = "", $startTime = "", $endTime = "", $itemsProcessed = 0)
  {
    $this->id = intval($id);
    $this->agent = $agent;
    $this->status = $status;
    $this->startTime = $startTime;
    $this->endTime = $endTime;
    $this->itemsProcessed = intval($itemsProcessed);
  }

  /**
   * JSON representation of current job queue
   * @return string
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  /**
   * Get Job Queue element as associative array
   * @return array
   */
  public function getArray()
  {
    return [
      'queueId'        => $this->id,
      'agentName'      => $this->agent,
      'status'         => $this->status,
      'startTime'      => $this->startTime,
      'endTime'        => $this->endTime,
      'itemsProcessed' => $this->itemsProcessed
    ];
  }

  /**
   * Get the job ID
   * @return number $id Job id
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * Get the agent name
   * @return string $agent Agent name
   */
  public function getAgent()
  {
    return $this->agent;
  }

  /**
   * Get job status
   * @return string $status Job Queue status
   */
  public function getStatus()
  {
    return $this->status;
  }

  /**
   * Get job start time
   * @return string $startTime Job start time
   */
  public function getStartTime()
  {
    return $this->startTime;
  }
  /**
   * Get job end time
   * @return string $endTime Job end time
   */
  public function getEndTime()
  {
    return $this->endTime;
  }
  /**
   * Get number of items processed
   * @return number $itemsProcessed Number of items processed
   */
  public function getItemsProcessed()
  {
    return $this->itemsProcessed;
  }

  /**
   * Set the agent name
   * @param string $agent Agent name
   */
  public function setAgent($agent)
  {
    $this->agent = $agent;
  }

  /**
   * Set the job queue status
   * @param string $status Job Queue status
   */
  public function setStatus($status)
  {
    $this->status = $status;
  }

  /**
   * Set the job queue status
   * @param string $startTime Start time of the job
   */
  public function setStartTime($startTime)
  {
    $this->startTime = $startTime;
  }

  /**
   * Set the job queue status
   * @param string $endTime End time of the job
   */
  public function setEndTime($endTime)
  {
    $this->endTime = $endTime;
  }

  /**
   * Set the no of items processed
   * @param number $itemsProcessed No of items processed
   */
  public function setItemsProcessed($itemsProcessed)
  {
    $this->itemsProcessed = $itemsProcessed;
  }
}
