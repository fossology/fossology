<?php
/*
 SPDX-FileCopyrightText: © 2023 Akash Kumar Sah <akashsah2003@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Agent model
 */
namespace Fossology\UI\Api\Models;

use Fossology\UI\Api\Models\ApiVersion;

class Agent
{
  /**
   * @var array $successfulAgents
   * Successful agents
   */
  private $successfulAgents;
  /**
   * @var integer $uploadId
   * Upload id
   */
  private $uploadId;
  /**
   * @var string $agentName
   * Agent's name
   */
  private $agentName;
  /**
   * @var integer $currentAgentId
   * Current agent id
   */
  private $currentAgentId;
  /**
   * @var string $currentAgentRev
   * Current agent's rev
   */
  private $currentAgentRev;
  /**
   * @var bool isAgentRunning
   * Agent running status
   */
  private $isAgentRunning;

  /**
   * @param integer $agentId
   * @param string $agentRev
   * @param string $agentName
   */
  public function __construct($successfulAgents, $uploadId, $agentName, $currentAgentId, $currentAgentRev, $isAgentRunning)
  {
    $this->successfulAgents = $successfulAgents;
    $this->uploadId = $uploadId;
    $this->agentName = $agentName;
    $this->currentAgentId = $currentAgentId;
    $this->currentAgentRev = $currentAgentRev;
  }

  /**
   * @return array
   */
  public function getSuccessfulAgents()
  {
    return $this->successfulAgents;
  }

  /**
   * @return integer
   */
  public function getUploadId()
  {
    return $this->uploadId;
  }

  /**
   * @return string
   */
  public function getAgentName()
  {
    return $this->agentName;
  }

  /**
   * @return integer
   */
  public function getCurrentAgentId()
  {
    return $this->currentAgentId;
  }

  /**
   * @return string
   */
  public function getCurrentAgentRev()
  {
    return $this->currentAgentRev;
  }

  /**
   * @return bool
   */
  public function getIsAgentRunning()
  {
    return $this->isAgentRunning;
  }


  /**
   * JSON representation of current scannedLicense
   * @param integer $version
   * @return string
   */
  public function getJSON($version=ApiVersion::V1)
  {
    return json_encode($this->getArray($version));
  }

  /**
   * Get ScannedLicense element as associative array
   * @param integer $version
   * @return array
   */
  public function getArray($version=ApiVersion::V1)
  {
    return [
      'successfulAgents' => $this->getSuccessfulAgents(),
      'uploadId' => $this->getUploadId(),
      'agentName' => $this->getAgentName(),
      'currentAgentId' => $this->getCurrentAgentId(),
      'currentAgentRev' => $this->getCurrentAgentRev(),
      'isAgentRunning' => $this->getIsAgentRunning()
    ];
  }

  /**
   * @param array $successfulAgents
   */
  public function setSuccessfulAgents($successfulAgents)
  {
    $this->successfulAgents = $successfulAgents;
  }

  /**
   * @param integer $uploadId
   */
  public function setUploadId($uploadId)
  {
    $this->uploadId = $uploadId;
  }

  /**
   * @param string $agentName
   */
  public function setAgentName($agentName)
  {
    $this->agentName = $agentName;
  }

  /**
   * @param integer $currentAgentId
   */
  public function setCurrentAgentId($currentAgentId)
  {
    $this->currentAgentId = $currentAgentId;
  }

  /**
   * @param string $currentAgentRev
   */
  public function setCurrentAgentRev($currentAgentRev)
  {
    $this->currentAgentRev = $currentAgentRev;
  }

  /**
   * @param bool $isAgentRunning
   */
  public function setIsAgentRunning($isAgentRunning)
  {
    $this->isAgentRunning = $isAgentRunning;
  }
}