<?php
/*
 SPDX-FileCopyrightText: © 2023 Akash Kumar Sah <akashsah2003@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief SuccessfulAgent model
 */
namespace Fossology\UI\Api\Models;


class SuccessfulAgent
{
  /**
   * @var integer $agentId
   * Agent id
   */
  private $agentId;
  /**
   * @var string $agentRev
   * Agent's rev
   */
  private $agentRev;
  /**
   * @var string $agentName
   * Agent's name
   */
  private $agentName;

  /**
   * @param integer $agentId
   * @param string $agentRev
   * @param string $agentName
   */
  public function __construct($agentId, $agentRev, $agentName)
  {
    $this->agentId = $agentId;
    $this->agentRev = $agentRev;
    $this->agentName = $agentName;
  }

  /**
   * @return integer
   */
  public function getAgentId()
  {
    return $this->agentId;
  }

  /**
   * @return string
   */
  public function getAgentRev()
  {
    return $this->agentRev;
  }

  /**
   * @return string
   */
  public function getAgentName()
  {
    return $this->agentName;
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
    if ($version == ApiVersion::V2) {
        return [
          'agentId' => $this->getAgentId(),
          'agentRev' => $this->getAgentRev(),
          'agentName' => $this->getAgentName()
        ];
    }
    return [
      'agent_id' => $this->getAgentId(),
      'agent_rev' => $this->getAgentRev(),
      'agent_name' => $this->getAgentName()
    ];
  }

  /**
   * @param integer $agentId
   */
  public function setAgentId($agentId)
  {
    $this->agentId = $agentId;
  }

  /**
   * @param string $agentRev
   */
  public function setAgentRev($agentRev)
  {
    $this->agentRev = $agentRev;
  }

  /**
   * @param string $agentName
   */
  public function setAgentName($agentName)
  {
    $this->agentName = $agentName;
  }
}