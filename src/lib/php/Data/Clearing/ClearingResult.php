<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Clearing;

use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Exception;

class ClearingResult implements LicenseClearing
{

  const AGENT_DECISION_TYPE = 'agent';

  /** @var ClearingEvent */
  private $clearingEvent;
  /** @var array|AgentClearingEvent[] */
  private $agentClearingEvents;

  /**
   * @param null|ClearingEvent $licenseDecisionEvent
   * @param AgentClearingEvent[] $agentDecisionEvents
   * @throws Exception
   */
  public function __construct($licenseDecisionEvent,
    $agentDecisionEvents = array())
  {
    if (($licenseDecisionEvent === null) && (count($agentDecisionEvents) == 0)) {
      throw new Exception(
        "cannot create ClearingEvent without any event contained");
    }

    $this->clearingEvent = $licenseDecisionEvent;
    $this->agentClearingEvents = $agentDecisionEvents;
  }

  /**
   * @return LicenseRef
   */
  public function getLicenseRef()
  {
    return $this->getClearing()->getLicenseRef();
  }

  /**
   * @throws Exception
   * @return int
   */
  function getLicenseId()
  {
    return $this->getClearing()->getLicenseId();
  }

  /**
   * @return string
   */
  function getLicenseFullName()
  {
    return $this->getClearing()->getLicenseFullName();
  }

  /**
   * @return string
   */
  function getLicenseShortName()
  {
    return $this->getClearing()->getLicenseShortName();
  }

  /**
   * @return string
   */
  public function getComment()
  {
    return isset($this->clearingEvent) ? $this->clearingEvent->getComment() : '';
  }

  /**
   * @return string
   */
  public function getEventType()
  {
    return $this->getClearing()->getEventType();
  }

  /**
   * @return string
   */
  public function getReportinfo()
  {
    return isset($this->clearingEvent) ? $this->clearingEvent->getReportinfo() : '';
  }

  /**
   * @return string
   */
  public function getAcknowledgement()
  {
    return isset($this->clearingEvent) ? $this->clearingEvent->getAcknowledgement() : '';
  }

  /**
   * @return boolean
   */
  public function isRemoved()
  {
    return $this->getClearing()->isRemoved();
  }

  /**
   * @throws Exception
   * @return LicenseClearing
   */
  private function getClearing()
  {
    if (isset($this->clearingEvent)) {
      return $this->clearingEvent;
    }

    return $this->agentClearingEvents[0];
  }

  /**
   * @return bool
   */
  public function hasAgentDecisionEvent()
  {
    return !empty($this->agentClearingEvents);
  }

  /**
   * @return bool
   */
  public function hasClearingEvent()
  {
    return isset($this->clearingEvent);
  }

  /**
   * @return array|AgentClearingEvent[]
   */
  public function getAgentDecisionEvents()
  {
    return $this->agentClearingEvents;
  }

  /**
   * @return ClearingEvent
   */
  public function getClearingEvent()
  {
    return $this->clearingEvent;
  }

  /*
   * @return int clearing timestamp
   */
  public function getTimeStamp()
  {
    return $this->getClearing()->getTimeStamp();
  }
}
