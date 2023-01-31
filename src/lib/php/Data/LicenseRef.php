<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

use Fossology\Lib\Dao\LicenseDao;

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
   * SPDX license ref prefix to use
   */
  const SPDXREF_PREFIX = "LicenseRef-fossology-";

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
      $spdxLicense = self::SPDXREF_PREFIX . $shortname;
    } else {
      $spdxLicense = $spdxId;
    }
    if (strpos($spdxLicense, LicenseRef::SPDXREF_PREFIX) !== false) {
      // License ref can not end with a '+'
      $spdxLicense = preg_replace('/\+$/', '-or-later', $spdxLicense);
    }
    return $spdxLicense;
  }
}
