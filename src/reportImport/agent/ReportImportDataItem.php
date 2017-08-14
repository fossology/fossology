<?php
/*
 * Copyright (C) 2017, Siemens AG
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
 */
namespace Fossology\ReportImport;

use Fossology\Lib\Data\License;

class ReportImportDataItem
{
  /** @var string */
  protected $licenseId;
  /** @var string */
  protected $customText = NULL;
  /** @var string */
  protected $comment = "";
  /** @var License */
  private $licenseCandidate = NULL;

  function __construct($licenseId)
  {
    $this->licenseId = $licenseId;
  }

  public function setCustomText($customText)
  {
    $this->customText = $customText;
    return $this;
  }

  /**
   * @param $name
   * @param $text
   * @param bool $spdxCompatible
   * @return $this
   */
  public function setLicenseCandidate($name, $text, $spdxCompatible)
  {
    $spdxCompatible = $spdxCompatible == true;
    $this->licenseCandidate = new License(
      $this->licenseId,
      $this->licenseId,
      $name,
      "",
      $text,
      "", // TODO: $this->getValue($license,'seeAlso'),
      "", // TODO
      $spdxCompatible);
    return $this;
  }

  /**
   * @return string
   */
  public function getLicenseId()
  {
    return $this->licenseId;
  }

  /**
   * @return bool
   */
  public function isSetCustomText()
  {
    return $this->customText !== NULL;
  }

  /**
   * @return string
   */
  public function getCustomText()
  {
    return $this->customText;
  }

  /**
   * @return string
   */
  public function getComment()
  {
    return $this->comment;
  }

  /**
   * @return bool
   */
  public function isSetLicenseCandidate()
  {
    return $this->licenseCandidate !== NULL;
  }

  /**
   * @return License
   */
  public function getLicenseCandidate()
  {
    return $this->licenseCandidate;
  }
}
