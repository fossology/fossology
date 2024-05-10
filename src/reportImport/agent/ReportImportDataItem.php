<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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
    $this->customText = trim($customText);
    return $this;
  }

  /**
   * @param $name
   * @param $text
   * @param bool $spdxCompatible
   * @param string $url
   * @return $this
   */
  public function setLicenseCandidate($name, $text, $spdxCompatible, $url = "")
  {
    $spdxCompatible = $spdxCompatible == true;
    $this->licenseCandidate = new License(
      $this->licenseId,
      $this->licenseId,
      $name,
      "",
      trim($text),
      $url,
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
