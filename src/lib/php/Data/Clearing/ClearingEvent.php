<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Clearing;

use Fossology\Lib\Data\LicenseRef;

class ClearingEvent implements LicenseClearing
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
  /** @var int */
  private $eventType;
  /** @var ClearingLicense */
  private $clearingLicense;

  /**
   * @param int $eventId
   * @param int $uploadTreeId
   * @param int $timestamp
   * @param int $userId
   * @param int $groupId
   * @param int $eventType
   * @param ClearingLicense $clearingLicense
   */
  public function __construct($eventId, $uploadTreeId, $timestamp, $userId, $groupId, $eventType, ClearingLicense $clearingLicense)
  {
    $this->eventId = $eventId;
    $this->uploadTreeId = $uploadTreeId;
    $this->timeStamp = $timestamp;
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
   * @return int
   */
  public function getTimeStamp()
  {
    return $this->timeStamp;
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
   */
  public function isRemoved()
  {
    return $this->clearingLicense->isRemoved();
  }

  /**
   * Get modified license text.
   *
   * @return string
   */
  public function getReportinfo()
  {
    return $this->clearingLicense->getReportinfo();
  }

  /**
   * Get acknowledgement
   *
   * @return string
   */
  public function getAcknowledgement()
  {
    return $this->clearingLicense->getAcknowledgement();
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
