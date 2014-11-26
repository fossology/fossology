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

class ClearingLicense extends Object
{

  private $licenseRef;
  /** @var boolean */
  private $removed;
  /** @var string */
  private $reportInfo;
  /** @var string */
  private $comment;
  /** @var int */
  private $type;

  /**
   * @param LicenseRef $licenseRef
   * @param boolean $removed
   * @param string $reportinfo
   * @param string $comment
   */
  public function __construct(LicenseRef $licenseRef, $removed, $type, $reportInfo = "", $comment = "")
  {
    $this->licenseRef = $licenseRef;
    $this->removed = $removed;
    $this->type = $type;
    $this->reportInfo = $reportInfo;
    $this->comment = $comment;
  }

  /**
   * @return ClearingLicense
   */
  public function copyNegated()
  {
    return new ClearingLicense($this->licenseRef, !($this->removed), $this->type, $this->reportInfo, $this->comment);
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

  public function __toString()
  {
    return "ClearingLicense(".($this->isRemoved() ? "-" : "").$this->getLicenseRef().",comment=".$this->comment.",reportinfo=".$this->reportInfo.")";

  }

}