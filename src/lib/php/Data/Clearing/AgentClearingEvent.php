<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Clearing;

use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\LicenseRef;

class AgentClearingEvent implements LicenseClearing
{
  /** @var LicenseRef */
  private $licenseRef;
  /** @var AgentRef */
  private $agentRef;
  /** @var int */
  private $matchId;
  /** @var int */
  private $percentage;

  /**
   * @param LicenseRef $licenseRef
   * @param AgentRef $agentRef
   * @param int $matchId
   * @param null|int $percentage
   */
  public function __construct(LicenseRef $licenseRef, AgentRef $agentRef,
    $matchId, $percentage)
  {
    $this->licenseRef = $licenseRef;
    $this->agentRef = $agentRef;
    $this->matchId = $matchId;
    $this->percentage = $percentage;
  }

  /**
   * @return int
   */
  public function getMatchId()
  {
    return $this->matchId;
  }

  /**
   * @return LicenseRef
   */
  public function getLicenseRef()
  {
    return $this->licenseRef;
  }

  /**
   * @return int
   */
  public function getLicenseId()
  {
    return $this->licenseRef->getId();
  }

  /**
   * @return string
   */
  public function getLicenseShortName()
  {
    return $this->licenseRef->getShortName();
  }

  /**
   * @return string
   */
  public function getLicenseFullName()
  {
    return $this->licenseRef->getFullName();
  }

  /**
   * @return string
   */
  public function getEventType()
  {
    return ClearingResult::AGENT_DECISION_TYPE;
  }

  /**
   * @return string
   */
  public function getComment()
  {
    return "";
  }

  /**
   * @return string
   */
  public function getReportinfo()
  {
    return "";
  }

  /**
   * @return string
   */
  public function getAcknowledgement()
  {
    return "";
  }

  /**
   * @return int $timeStamp
   */
  public function getTimeStamp()
  {
    return time();
  }

  /**
   * @return boolean
   */
  public function isRemoved()
  {
    return false;
  }

  /**
   * @return AgentRef
   */
  public function getAgentRef()
  {
    return $this->agentRef;
  }

  /**
   * @return string
   */
  public function getAgentName()
  {
    return $this->agentRef->getAgentName();
  }

  /**
   * @return int
   */
  public function getAgentId()
  {
    return $this->agentRef->getAgentId();
  }

  public function getPercentage()
  {
    return $this->percentage;
  }
}
