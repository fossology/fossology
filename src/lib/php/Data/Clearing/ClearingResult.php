<?php
/*
Copyright (C) 2014, Siemens AG

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
*/

namespace Fossology\Lib\Data\Clearing;


use DateTime;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Exception;

class ClearingResult implements LicenseClearing {
  const AGENT_DECISION_TYPE = 'agent';

  /**
   * @var ClearingEvent
   */
  private $clearingEvent;

  /**
   * @var array|AgentClearingEvent[]
   */
  private $agentClearingEvents;

  /**
   * @param null|ClearingEvent $licenseDecisionEvent
   * @param AgentClearingEvent[] $agentDecisionEvents
   * @throws Exception
   */
  public function __construct($licenseDecisionEvent, $agentDecisionEvents=array()) {
    if (($licenseDecisionEvent === null) && (count($agentDecisionEvents) == 0)) {
      throw new Exception("cannot create ClearingEvent without any event contained");
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
    return isset($this->clearingEvent) ? $this->getClearing()->getComment() : '';
  }

  /**
   * @return DateTime
   */
  public function getDateTime()
  {
    return $this->getClearing()->getDateTime();
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
   * @return int EXTRACT(EPOCH FROM getClearingEvent()->getDateTime())
   */
  public function getTimestamp()
  {
    $dateTime = $this->getDateTime();
    return $dateTime->getTimestamp();
  }
  
}