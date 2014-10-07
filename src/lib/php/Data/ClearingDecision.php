<?php
/*
Copyright (C) 2014, Siemens AG
Author: Johannes Najjar

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

class ClearingDecision extends ClearingDecisionData
{

  const NOT_DECIDED = "Not decided";
  const NO_LICENSE_KNOWN = "No license known";
  const TO_BE_DISCUSSED = "To be discussed";
  const IRRELEVANT = "Irrelevant";
  const IDENTIFIED = "Identified";


  public function __construct($sameFolder, $sameUpload, $clearingId, $uploadTreeId, $pfileId, $userName, $userId, $type, $scope, $date_added, $licenses, $comment = "", $reportinfo = "")
  {
    $this->sameFolder = $sameFolder;
    $this->sameUpload = $sameUpload;
    $this->clearingId = $clearingId;
    $this->uploadTreeId = $uploadTreeId;
    $this->pfileId = $pfileId;
    $this->userName = $userName;
    $this->userId = $userId;
    $this->type = $type;
    $this->scope = $scope;
    $this->date_added = $date_added;
    $this->comment = $comment;
    $this->reportinfo = $reportinfo;
    $this->licenses = $licenses;
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
    return $this->date_added;
  }

  /**
   * @return \Fossology\Lib\Data\LicenseRef[]
   */
  public function getLicenses()
  {
    return $this->licenses;
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
   * @return boolean
   */
  public function getSameUpload()
  {
    return $this->sameUpload;
  }

  /**
   * @return string
   */
  public function getScope()
  {
    return $this->scope;
  }

  /**
   * @return string
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


}