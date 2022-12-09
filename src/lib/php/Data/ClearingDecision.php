<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG
 Author: Johannes Najjar, Steffen Weber

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingLicense;

class ClearingDecision
{
  /** @var bool */
  private $sameFolder;
  /** @var ClearingEvent[] */
  private $clearingEvents;
  /** @var int */
  private $clearingId;
  /** @var int */
  private $uploadTreeId;
  /** @var int */
  private $pfileId;
  /** @var string */
  private $userName;
  /** @var int */
  private $userId;
  /** @var int */
  private $type;
  /** @var string */
  private $comment;
  /** @var string */
  private $reportinfo;
  /** @var string */
  private $acknowledgement;
  /** @var int */
  private $scope;
  /** @var int */
  private $timeStamp;

  /**
   * @param $sameFolder
   * @param int $clearingId
   * @param $uploadTreeId
   * @param $pfileId
   * @param $userName
   * @param $userId
   * @param int $type
   * @param int $scope
   * @param $ts_added
   * @param ClearingEvent[] $clearingEvents
   * @param string $comment
   * @param string $reportinfo
   * @param string $acknowledgement
   * @internal param $licenses
   */
  public function __construct($sameFolder, $clearingId, $uploadTreeId, $pfileId, $userName, $userId, $type, $scope, $ts_added, $clearingEvents, $comment = "", $reportinfo = "", $acknowledgement = "")
  {
    $this->sameFolder = $sameFolder;
    $this->clearingId = $clearingId;
    $this->uploadTreeId = $uploadTreeId;
    $this->pfileId = $pfileId;
    $this->userName = $userName;
    $this->userId = $userId;
    $this->type = $type;
    $this->scope = $scope;
    $this->timeStamp = $ts_added;
    $this->comment = $comment;
    $this->reportinfo = $reportinfo;
    $this->acknowledgement = $acknowledgement;
    $this->clearingEvents = $clearingEvents;
  }

  /**
   * @return int
   */
  public function getClearingId()
  {
    return $this->clearingId;
  }

  /**
   * @return string
   */
  public function getComment()
  {
    return $this->comment;
  }

  /**
   * @return int
   */
  public function getTimeStamp()
  {
    return $this->timeStamp;
  }

  /**
   * @return ClearingLicense[]
   */
  public function getClearingLicenses()
  {
    $clearingLicenses = array();
    foreach ($this->clearingEvents as $clearingEvent) {
      $clearingLicenses[] = $clearingEvent->getClearingLicense();
    }
    return $clearingLicenses;
  }

  /**
   * @return LicenseRef[]
   */
  public function getPositiveLicenses()
  {
    $result = array();
    foreach ($this->clearingEvents as $clearingEvent) {
      $clearingLicense = $clearingEvent->getClearingLicense();
      if (! $clearingLicense->isRemoved()) {
        $result[] = $clearingLicense->getLicenseRef();
      }
    }

    return $result;
  }

  /**
   * @return ClearingEvent[]
   */
  public function getClearingEvents()
  {
    return $this->clearingEvents;
  }

  /**
   * @return int
   */
  public function getPfileId()
  {
    return $this->pfileId;
  }

  /**
   * @return string
   */
  public function getReportinfo()
  {
    return $this->reportinfo;
  }

  /**
   * @return string
   */
  public function getAcknowledgement()
  {
    return $this->acknowledgement;
  }

  /**
   * @return boolean
   */
  public function getSameFolder()
  {
    return $this->sameFolder;
  }

  /**
   * @return int
   */
  public function getScope()
  {
    return $this->scope;
  }

  /**
   * @return int
   */
  public function getType()
  {
    return $this->type;
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
   * @return string
   */
  public function getUserName()
  {
    return $this->userName;
  }

  /**
   * @return bool
   */
  public function isInScope()
  {
    switch ($this->getScope()) {
      case 'global':
        return true;
      case 'upload':
        return $this->sameFolder;
    }
    return false;
  }

  function __toString()
  {
    $output = "ClearingDecision(#" . $this->clearingId . ", ";

    $clearingLicenses = $this->getClearingLicenses();

    foreach ($clearingLicenses as $clearingLicense) {
      $output .= ($clearingLicense->isRemoved() ? "-" : ""). $clearingLicense->getShortName() . ", ";
    }

    return $output . $this->getUserName() . ")";
  }
}
