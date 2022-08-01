<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG
 Author: J.Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Clearing;

use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;

class ClearingEventBuilder
{
  /** @var int */
  private $eventId;
  /** @var int */
  private $uploadTreeId;
  /** @var int */
  private $timeStamp;
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
  /** @var string */
  private $acknowledgement;

  public function __construct()
  {
    $this->eventId = 0;
    $this->uploadTreeId = 0;
    $this->timeStamp = null;
    $this->userId = 1;
    $this->groupId = 1;
    $this->eventType = ClearingEventTypes::USER;
    $this->licenseRef = null;
    $this->removed = false;
    $this->reportinfo = "";
    $this->comment = "";
    $this->acknowledgement = "";
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
    $clearingLicense = new ClearingLicense($this->licenseRef, $this->removed, $this->eventType, $this->reportinfo, $this->comment, $this->acknowledgement);
    return new ClearingEvent($this->eventId, $this->uploadTreeId, $this->timeStamp?: time(), $this->userId, $this->groupId, $this->eventType, $clearingLicense);
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
   * @param int $timestamp
   * @return $this
   */
  public function setTimeStamp($timestamp)
  {
    $this->timeStamp = $timestamp;
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
   * @param string $acknowledgement
   * @return $this
   */
  public function setAcknowledgement($acknowledgement)
  {
    $this->acknowledgement = $acknowledgement;
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
