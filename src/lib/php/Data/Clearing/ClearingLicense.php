<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Clearing;

use Fossology\Lib\Data\LicenseRef;

class ClearingLicense
{

  private $licenseRef;
  /** @var boolean */
  private $removed;
  /** @var string */
  private $reportInfo;
  /** @var string */
  private $acknowledgement;
  /** @var string */
  private $comment;
  /** @var int */
  private $type;

  /**
   * @param LicenseRef $licenseRef
   * @param boolean $removed
   * @param $type
   * @param string $reportInfo
   * @param string $comment
   * @param string $acknowledgement
   */
  public function __construct(LicenseRef $licenseRef, $removed, $type, $reportInfo = "", $comment = "", $acknowledgement = "")
  {
    $this->licenseRef = $licenseRef;
    $this->removed = $removed;
    $this->type = $type;
    $this->reportInfo = $reportInfo;
    $this->acknowledgement = $acknowledgement;
    $this->comment = $comment;
  }

  /**
   * @return ClearingLicense
   */
  public function copyNegated()
  {
    return new ClearingLicense($this->licenseRef, ! ($this->removed),
      $this->type, $this->reportInfo, $this->comment, $this->acknowledgement);
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
  public function getLicenseId()
  {
    return $this->getLicenseRef()->getId();
  }

  /**
   * @return string
   */
  public function getFullName()
  {
    return $this->getLicenseRef()->getFullName();
  }

  /**
   * @return string
   */
  public function getShortName()
  {
    return $this->getLicenseRef()->getShortName();
  }

  /**
   * @return string
   */
  public function getSpdxId()
  {
    return $this->getLicenseRef()->getSpdxId();
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
  public function getType()
  {
    return $this->type;
  }

  /**
   * @return string
   */
  public function getComment()
  {
    return $this->comment;
  }

  /**
   * @return string
   */
  public function getReportinfo()
  {
    return $this->reportInfo;
  }

  /**
   * @return string
   */
  public function getAcknowledgement()
  {
    return $this->acknowledgement;
  }

  public function __toString()
  {
    $eventTypes = new ClearingEventTypes();
    return "ClearingLicense("
       .($this->isRemoved() ? "-" : "")
       .$this->getLicenseRef()
       .",type='".($eventTypes->getTypeName($this->type))."'(".$this->type.")"
       .",comment='".$this->comment."'"
       .",reportinfo='".$this->reportInfo."'"
       .",acknowledgement='".$this->acknowledgement."'"
      .")";

  }
}
