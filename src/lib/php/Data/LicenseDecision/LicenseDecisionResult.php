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

namespace Fossology\Lib\Dao\Data\LicenseDecision;


class LicenseDecisionResult implements LicenseDecision {
  /**
   * @var LicenseDecisionEvent
   */
  private $licenseDecisionEvent;

  /**
   * @var array|AgentLicenseDecisionEvent[]
   */
  private $agentDecisionEvents;

  /**
   * @param LicenseDecisionEvent $licenseDecisionEvent
   * @param AgentLicenseDecisionEvent[] $agentDecisionEvents
   */
  public function __construct(LicenseDecisionEvent $licenseDecisionEvent, $agentDecisionEvents=array()) {

    $this->licenseDecisionEvent = $licenseDecisionEvent;
    $this->agentDecisionEvents = $agentDecisionEvents;
  }

  /**
   * @return int
   */
  function getId()
  {
    // TODO: Implement getId() method.
  }

  /**
   * @return string
   */
  function getFullName()
  {
    // TODO: Implement getFullName() method.
  }

  /**
   * @return string
   */
  function getShortName()
  {
    // TODO: Implement getShortName() method.
  }

  /**
   * @return string
   */
  public function getComment()
  {
    // TODO: Implement getComment() method.
  }

  /**
   * @return float
   */
  public function getEpoch()
  {
    // TODO: Implement getEpoch() method.
  }

  /**
   * @return int
   */
  public function getEventId()
  {
    // TODO: Implement getEventId() method.
  }

  /**
   * @return string
   */
  public function getEventType()
  {
    // TODO: Implement getEventType() method.
  }

  /**
   * @return int
   */
  public function getLicenseId()
  {
    // TODO: Implement getLicenseId() method.
  }

  /**
   * @return string
   */
  public function getLicenseShortName()
  {
    // TODO: Implement getLicenseShortName() method.
  }

  /**
   * @return string
   */
  public function getReportinfo()
  {
    // TODO: Implement getReportinfo() method.
  }

  /**
   * @return boolean
   */
  public function isGlobal()
  {
    // TODO: Implement isGlobal() method.
  }

  /**
   * @return boolean
   */
  public function isRemoved()
  {
    // TODO: Implement isRemoved() method.
  }
}