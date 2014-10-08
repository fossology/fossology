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

namespace Fossology\Lib\Data\LicenseDecision;

use Fossology\Lib\Data\LicenseRef;

class LicenseDecisionEvent implements LicenseDecision
{

  /** @var int */
  private $eventId;

  /** @var LicenseRef */
  private $licenseRef;

  /** @var string */
  private $eventType;

  /** @var double */
  private $epoch;

  /** @var string */
  private $reportinfo;

  /** @var string */
  private $comment;

  /** @var boolean */
  private $global;

  /** @var boolean */
  private $removed;

  /**
   * @param string $eventId
   * @param LicenseRef $licenseRef
   * @param string $eventType
   * @param string $epoch
   * @param string $reportinfo
   * @param string $comment
   * @param boolean $global
   * @param boolean $removed
   */
  public function __construct($eventId, LicenseRef $licenseRef, $eventType, $epoch, $reportinfo, $comment, $global, $removed) {
    $this->eventId = intval($eventId);
    $this->licenseRef = $licenseRef;
    $this->eventType = $eventType;
    $this->epoch = $epoch;
    $this->reportinfo = $reportinfo;
    $this->comment = $comment;
    $this->global = $global;
    $this->removed = $removed;
  }

  /**
   * @return string
   */
  public function getComment()
  {
    return $this->comment;
  }

  /**
   * @return float
   */
  public function getEpoch()
  {
    return $this->epoch;
  }

  /**
   * @return int
   */
  public function getEventId()
  {
    return $this->eventId;
  }

  /**
   * @return string
   */
  public function getEventType()
  {
    return $this->eventType;
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
  public function getReportinfo()
  {
    return $this->reportinfo;
  }

  /**
   * @return boolean
   */
  public function isGlobal()
  {
    return $this->global;
  }

  /**
   * @return boolean
   */
  public function isRemoved()
  {
    return $this->removed;
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
}