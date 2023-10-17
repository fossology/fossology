<?php
/*
 SPDX-FileCopyrightText: © 2023 Siemens AG
 SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
 */

namespace Fossology\Lib\Data\Report;

use Fossology\Lib\Data\License;

class SpdxLicenseInfo
{
  /**
   * @var License $licenseObj
   * License object to get data from.
   */
  private $licenseObj;
  /**
   * @var bool $listedLicense
   * Is a SPDX listed license?
   */
  private $listedLicense = false;
  /**
   * @var bool $customText
   * Is a custom text license?
   */
  private $customText = false;

  /**
   * @return bool
   */
  public function isTextPrinted(): bool
  {
    return $this->textPrinted;
  }

  /**
   * @param bool $textPrinted
   * @return SpdxLicenseInfo
   */
  public function setTextPrinted(bool $textPrinted): SpdxLicenseInfo
  {
    $this->textPrinted = $textPrinted;
    return $this;
  }
  /**
   * @var bool $textPrinted
   * Is the license text already printed?
   */
  private $textPrinted = false;

  /**
   * @return License
   */
  public function getLicenseObj(): License
  {
    return $this->licenseObj;
  }

  /**
   * @param License $licenseObj
   * @return SpdxLicenseInfo
   */
  public function setLicenseObj(License $licenseObj): SpdxLicenseInfo
  {
    $this->licenseObj = $licenseObj;
    return $this;
  }

  /**
   * @return bool
   */
  public function isListedLicense(): bool
  {
    return $this->listedLicense;
  }

  /**
   * @param bool $listedLicense
   * @return SpdxLicenseInfo
   */
  public function setListedLicense(bool $listedLicense): SpdxLicenseInfo
  {
    $this->listedLicense = $listedLicense;
    return $this;
  }

  /**
   * @return bool
   */
  public function isCustomText(): bool
  {
    return $this->customText;
  }

  /**
   * @param bool $customText
   * @return SpdxLicenseInfo
   */
  public function setCustomText(bool $customText): SpdxLicenseInfo
  {
    $this->customText = $customText;
    return $this;
  }
}
