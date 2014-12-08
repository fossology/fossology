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
use Fossology\Lib\Util\Object;

class ClearingEvent extends Object implements LicenseClearing
{
  /** @var int */
  private $eventId;
  /** @var int */
  private $uploadTreeId;
  /** @var DateTime */
  private $dateTime;
  /** @var int */
  private $userId;
  /** @var int */
  private $groupId;
  /** @var int */
  private $eventType;
  /** @var ClearingLicense */
  private $clearingLicense;

  /**
   * @param int $eventId
   * @param int $uploadTreeId
   * @param DateTime $dateTime
   * @param int $userId
   * @param int $groupId
   * @param int $eventType
   * @param ClearingLicense $clearingLicense
   * @param boolean $removed
   * @param string $reportinfo
   * @param string $comment
   */
  public function __construct($eventId, $uploadTreeId, $dateTime, $userId, $groupId, $eventType, ClearingLicense $clearingLicense)
  {
    $this->eventId = $eventId;
    $this->uploadTreeId = $uploadTreeId;
    $this->dateTime = $dateTime;
    $this->userId = $userId;
    $this->groupId = $groupId;
    $this->eventType = $eventType;
    $this->clearingLicense = $clearingLicense;
  }

  /**
   * @return ClearingLicense
   */
  public function getClearingLicense()
  {
    return $this->clearingLicense;
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
   * @return int
   */
  public function getEventType()
  {
    return $this->eventType;
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
   * @deprecated
   */
  public function getLicenseRef()
  {
    return $this->clearingLicense->getLicenseRef();
  }

  /**
   * @return boolean
   * @deprecated
   */
  public function isRemoved()
  {
    return $this->clearingLicense->isRemoved();
  }

  /**
   * @return string
   * @deprecated
   */
  public function getReportinfo()
  {
    return $this->clearingLicense->getReportinfo();
  }

  /**
   * @return string
   * @deprecated
   */
  public function getComment()
  {
    return $this->clearingLicense->getComment();
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
    return $this->clearingLicense->getLicenseId();
  }

  /**
   * @return string
   */
  public function getLicenseShortName()
  {
    return $this->clearingLicense->getShortName();
  }

  /**
   * @return string
   */
  public function getLicenseFullName()
  {
    return $this->clearingLicense->getFullName();
  }
}