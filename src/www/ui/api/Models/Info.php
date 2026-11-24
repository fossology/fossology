<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Info model
 */

namespace Fossology\UI\Api\Models;

/**
 * @class Info
 * @brief Info model to contain general error and return values
 */
class Info
{
  /**
   * @var integer $code
   * HTTP response code
   */
  private $code;
  /**
   * @var string $message
   * Reponse message
   */
  private $message;
  /**
   * @var InfoType $type
   * Response type
   */
  private $type;
  /**
   * @var string[]|null $scheduledAgents
   * Newly scheduled agents
   */
  private $scheduledAgents;
  /**
   * @var string[]|null $skippedAgents
   * Skipped agents
   */
  private $skippedAgents;
  /**
   * Error constructor.
   * @param integer $code
   * @param string $message
   * @param InfoType $type
   * @param string[]|null $scheduledAgents
   * @param string[]|null $skippedAgents
   */
  public function __construct($code, $message, $type, $scheduledAgents = null,
      $skippedAgents = null)
  {
    $this->code = $code;
    $this->message = $message;
    $this->type = $type;
    $this->scheduledAgents = $scheduledAgents;
    $this->skippedAgents = $skippedAgents;
  }

  ////// Getters //////

  /**
   * Get the info as JSON representation
   * @return string
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  /**
   * Get info as associative array
   * @return array
   */
  public function getArray()
  {
    $info = [
      'code' => $this->code,
      'message' => $this->message
    ];
    if ($this->scheduledAgents !== null) {
      $info['scheduled_agents'] = $this->scheduledAgents;
    }
    if ($this->skippedAgents !== null) {
      $info['skipped_agents'] = $this->skippedAgents;
    }
    $info['type'] = $this->type;
    return $info;
  }

  /**
   * @return integer
   */
  public function getCode()
  {
    return $this->code;
  }

  /**
   * @return string
   */
  public function getMessage()
  {
    return $this->message;
  }

  /**
   * @return InfoType
   */
  public function getType()
  {
    return $this->type;
  }
}
