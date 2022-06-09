<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Authors: Johannes Najjar, Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

class License extends LicenseRef
{
  /**
   * @var string
   */
  private $text;
  /**
   * @var string
   */
  private $url;
  /**
   * @var string
   */
  private $risk;
  /**
   * @var string
   */
  private $detectorType;
  /**
   * @var string
   */
  private $spdxCompatible;

  function __construct($id, $shortName, $fullName, $risk, $text, $url, $detectorType, $spdxCompatible = false)
  {
    parent::__construct($id, $shortName, $fullName);
    $this->text = $text;
    $this->url = $url;
    $this->risk = $risk;
    $this->detectorType = $detectorType;
    $this->spdxCompatible = $spdxCompatible;
  }

  /**
   * @return int
   */
  public function getRisk()
  {
    return $this->risk;
  }

  /**
   * @return int
   */
  public function getDetectorType()
  {
    return $this->detectorType;
  }

  /**
   * @return boolean
   */
  public function getSpdxCompatible()
  {
    return $this->spdxCompatible;
  }

  /**
   * @return string
   */
  public function getText()
  {
    return $this->text;
  }

  /**
   * @return string
   */
  public function getUrl()
  {
    return $this->url;
  }

  /** @return LicenseRef */
  public function getRef()
  {
    return new parent($this->getId(), $this->getShortName(), $this->getFullName());
  }
}
