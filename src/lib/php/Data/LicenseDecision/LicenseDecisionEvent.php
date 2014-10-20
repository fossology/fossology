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

use DateTime;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Util\Object;

class LicenseDecisionEvent extends Object implements LicenseDecision
{

  /** @var int */
  private $eventId;

  /** @var int */
  private $pfileId;

  /** @var int */
  private $uploadTreeId;

  /** @var DateTime */
  private $dateTime;

  /** @var int */
  private $userId;

  /** @var int */
  private $groupId;

  /** @var string */
  private $eventType;

  /** @var LicenseRef */
  private $licenseRef;

  /** @var boolean */
  private $global;

  /** @var boolean */
  private $removed;

  /** @var string */
  private $reportinfo;

  /** @var string */
  private $comment;


  /**
   * @param int $eventId
   * @param int $pfileId
   * @param int $uploadTreeId
   * @param DateTime $dateTime
   * @param int $userId
   * @param int $groupId
   * @param string $eventType
   * @param LicenseRef $licenseRef
   * @param boolean $global
   * @param boolean $removed
   * @param string $reportinfo
   * @param string $comment
   */
  public function __construct($eventId, $pfileId, $uploadTreeId, $dateTime, $userId, $groupId, $eventType, LicenseRef $licenseRef, $global, $removed, $reportinfo, $comment)
  {
    $this->eventId = $eventId;
    $this->pfileId = $pfileId;
    $this->uploadTreeId = $uploadTreeId;
    $this->dateTime = $dateTime;
    $this->userId = $userId;
    $this->groupId = $groupId;
    $this->eventType = $eventType;
    $this->licenseRef = $licenseRef;
    $this->global = $global;
    $this->removed = $removed;
    $this->reportinfo = $reportinfo;
    $this->comment = $comment;
  }

  /**
   * @return string
   */
  public function getComment()
  {
    return $this->comment;
  }

  /**
   * @return DateTime
   */
  public function getDateTime()
  {
    return $this->dateTime;
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
   * @return boolean
   */
  public function isGlobal()
  {
    return $this->global;
  }

  /**
   * @return int
   */
  public function getGroupId()
  {
    return $this->groupId;
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
  public function getPfileId()
  {
    return $this->pfileId;
  }

  /**
   * @return boolean
   */
  public function isRemoved()
  {
    return $this->removed;
  }

  /**
   * @return string
   */
  public function getReportinfo()
  {
    return $this->reportinfo;
  }

  /**
   * @return int
   */
  public function getUploadTreeId()
  {
    return $this->uploadTreeId;
  }

  /**
   * @return int
   */
  public function getUserId()
  {
    return $this->userId;
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

}