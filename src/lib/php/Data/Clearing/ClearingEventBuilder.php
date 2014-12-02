<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
 * Author: J.Najjar
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\Lib\Data\Clearing;


use DateTime;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Util\Object;


class ClearingEventBuilder extends Object
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
  /** @var string */
  private $eventType;
  /** @var LicenseRef */
  private $licenseRef;
  /** @var boolean */
  private $removed;
  /** @var string */
  private $reportinfo;
  /** @var string */
  private $comment;

  public function __construct()
  {
    $this->eventId = 0;
    $this->uploadTreeId = 0;
    $this->dateTime = null;
    $this->userId = 1;
    $this->groupId = 1;
    $this->eventType = ClearingEventTypes::USER;
    $this->licenseRef = null;
    $this->removed = false;
    $this->reportinfo = "";
    $this->comment = "";
  }

  public static function create()
  {
    return new ClearingEventBuilder();
  }

  /**
   * @return ClearingEvent
   */
  public function build()
  {
    //TODO change setters
    $clearingLicense = new ClearingLicense($this->licenseRef, $this->removed, $this->eventType, $this->reportinfo, $this->comment );
    return new ClearingEvent($this->eventId, $this->uploadTreeId, $this->dateTime, $this->userId,
        $this->groupId, $this->eventType, $clearingLicense);
  }

  /**
   * @param string $comment
   * @return $this
   */
  public function setComment($comment)
  {
    $this->comment = $comment;
    return $this;
  }

  /**
   * @param string $dateTime
   * @return $this
   */
  public function setDateFromTimeStamp($dateTime)
  {
    if ($this->dateTime === null) {
      $this->dateTime = new DateTime();
    }
    $this->dateTime->setTimestamp($dateTime);
    return $this;
  }

  /**
   * @param int $eventId
   * @return $this
   */
  public function setEventId($eventId)
  {
    $this->eventId = intval($eventId);
    return $this;
  }

  /**
   * @param string $eventType
   * @return $this
   */
  public function setEventType($eventType)
  {
    $this->eventType = $eventType;
    return $this;
  }

  /**
   * @param int $groupId
   * @return $this
   */
  public function setGroupId($groupId)
  {
    $this->groupId = intval($groupId);
    return $this;
  }

  /**
   * @param LicenseRef $licenseRef
   * @return $this
   */
  public function setLicenseRef(LicenseRef $licenseRef)
  {
    $this->licenseRef = $licenseRef;
    return $this;
  }

  /**
   * @param boolean $removed
   * @return $this
   */
  public function setRemoved($removed)
  {
    $this->removed = $removed;
    return $this;
  }

  /**
   * @param string $reportinfo
   * @return $this
   */
  public function setReportinfo($reportinfo)
  {
    $this->reportinfo = $reportinfo;
    return $this;
  }

  /**
   * @param int $uploadTreeId
   * @return $this
   */
  public function setUploadTreeId($uploadTreeId)
  {
    $this->uploadTreeId = intval($uploadTreeId);
    return $this;
  }

  /**
   * @param int $userId
   * @return $this
   */
  public function setUserId($userId)
  {
    $this->userId = intval($userId);
    return $this;
  }

}