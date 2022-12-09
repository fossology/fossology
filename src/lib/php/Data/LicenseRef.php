<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

class LicenseRef
{
  /** @var int */
  private $id;

  /** @var string */
  private $shortName;

  /** @var string */
  private $fullName;

  /**
   * @param $licenseId
   * @param $licenseShortName
   * @param $licenseName
   */
  function __construct($licenseId, $licenseShortName, $licenseName)
  {
    $this->id = $licenseId;
    $this->shortName = $licenseShortName;
    $this->fullName = $licenseName ? : $licenseShortName;
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

  public function __toString()
  {
    return 'LicenseRef('
      .$this->id
      .", ".$this->shortName
      .", ".$this->fullName
    .')';
  }
}
