<?php
/*
Copyright (C) 2014, Siemens AG
Author: Johannes Najjar, Steffen Weber

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

namespace Fossology\Lib\Data;

use DateTime;
use Fossology\Lib\Util\Object;

class ClearingDecision extends Object
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
  /** @var int */
  private $scope;
  /** @var DateTime */
  private $dateAdded;

  /**
   * @param $sameFolder
   * @param int $clearingId
   * @param $uploadTreeId
   * @param $pfileId
   * @param $userName
   * @param $userId
   * @param int $type
   * @param int $scope
   * @param $date_added
   * @param ClearingEvent[] $clearingEvents
   * @param string $comment
   * @param string $reportinfo
   * @internal param $licenses
   */
  public function __construct($sameFolder, $clearingId, $uploadTreeId, $pfileId, $userName, $userId, $type,
          $scope, $date_added, $clearingEvents, $comment = "", $reportinfo = "")
  {
    $this->sameFolder = $sameFolder;
    $this->clearingId = $clearingId;
    $this->uploadTreeId = $uploadTreeId;
    $this->pfileId = $pfileId;
    $this->userName = $userName;
    $this->userId = $userId;
    $this->type = $type;
    $this->scope = $scope;
    $this->dateAdded = $date_added;
    $this->comment = $comment;
    $this->reportinfo = $reportinfo;
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
   * @return DateTime
   */
  public function getDateAdded()
  {
    return $this->dateAdded;
  }

  /**
   * @return ClearingLicense[]
   */
  public function getClearingLicenses()
  {
    $clearingLicenses = array();
    foreach($this->clearingEvents as $clearingEvent) {
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
    foreach($this->getClearingLicenses() as $clearingLicense)
    {
      if (!$clearingLicense->isRemoved())
      {
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
    switch ($this->getScope())
    {
      case 'global': return true;
      case 'upload': return $this->sameFolder;
    }
    return false;
  }
  
  /**
   * @return int
   */
  public function getTimeStamp()
  {
    return $this->dateAdded->getTimestamp();
  }

  function __toString()
  {
    $output = "ClearingDecision(#" . $this->clearingId . ", ";

    foreach ($this->clearingLicenses as $clearingLicense) {
      $output .= ($clearingLicense->isRemoved() ? "-": "" ). $clearingLicense->getShortName() . ", ";
    }

    return $output . $this->getUserName() . ")";
  }


}