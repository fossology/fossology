<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Util\StringOperation;

class LicenseRef
{
  /** @var int */
  private $id;

  /** @var string */
  private $shortName;

  /** @var string */
  private $fullName;

  /** @var string */
  private $spdxId;

  /**
   * @var string
   * SPDX license ref prefix
   */
  const SPDXREF_PREFIX = "LicenseRef-";
  /**
   * @var string
   * SPDX license ref prefix to use
   */
  const SPDXREF_PREFIX_FOSSOLOGY = "LicenseRef-fossology-";

  /**
   * @param $licenseId
   * @param $licenseShortName
   * @param $licenseName
   * @param string $spdxId
   */
  function __construct($licenseId, $licenseShortName, $licenseName, $spdxId)
  {
    $this->id = $licenseId;
    $this->shortName = $licenseShortName;
    $this->fullName = $licenseName ? : $licenseShortName;
    $this->spdxId = self::convertToSpdxId($this->shortName, $spdxId);
  }

  /**
   * @return int
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @return string
   */
  public function getFullName()
  {
    return $this->fullName;
  }

  /**
   * @return string
   */
  public function getShortName()
  {
    return $this->shortName;
  }

  /**
   * @return string
   */
  public function getSpdxId()
  {
    return $this->spdxId;
  }

  public function __toString()
  {
    return 'LicenseRef('
      .$this->id
      .", ".$this->spdxId
      .", ".$this->shortName
      .", ".$this->fullName
    .')';
  }

  /**
   * @brief Given a license's shortname and spdx id, give out spdx id to use in
   *        reports.
   *
   * - In case, the shortname is special, return as is.
   * - In case spdx id is empty, return shortname with spdx prefix.
   * - Otherwise use the provided spdx id
   * @param string $shortname   License's shortname from DB
   * @param string|null $spdxId License's spdx id from DB
   * @return string
   */
  public static function convertToSpdxId($shortname, $spdxId): string
  {
    if (strcasecmp($shortname, LicenseDao::NO_LICENSE_FOUND) === 0 ||
        strcasecmp($shortname, LicenseDao::VOID_LICENSE) === 0) {
      $spdxLicense = $shortname;
    } elseif (empty($spdxId)) {
      $spdxLicense = $shortname;
      if (! StringOperation::stringStartsWith($shortname, self::SPDXREF_PREFIX)) {
        $spdxLicense = self::SPDXREF_PREFIX_FOSSOLOGY . $shortname;
      }
    } else {
      $spdxLicense = $spdxId;
    }
    if (StringOperation::stringStartsWith($spdxLicense, self::SPDXREF_PREFIX)) {
      // License ref can not end with a '+'
      $spdxLicense = preg_replace('/\+$/', '-or-later', $spdxLicense);
    }
    $spdxLicense = self::replaceSpaces($spdxLicense);
    
    if (StringOperation::stringStartsWith($spdxLicense, self::SPDXREF_PREFIX)) {
      $spdxLicense = SpdxLicenseValidator::sanitizeLicenseRef($spdxLicense);
    }
    
    return $spdxLicense;
  }

  /**
   * Replace all spaces with '-' if they are not surrounding 'AND', 'WITH' or
   * 'OR'
   * @param string $licenseName SPDX expression
   * @return string SPDX expression with space replaced with dash
   */
  public static function replaceSpaces($licenseName): string
  {
    $licenseName = str_replace(' ', '-', $licenseName);
    return preg_replace('/-(OR|AND|WITH)-(?!later)/i', ' $1 ', $licenseName);
  }
}
