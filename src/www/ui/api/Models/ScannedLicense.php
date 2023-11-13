<?php
/*
 SPDX-FileCopyrightText: © 2023 Samuel Dushimimana <dushsam100@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief ScannedLicense model
 */
namespace Fossology\UI\Api\Models;

class ScannedLicense
{
  /**
   * @var integer $id
   * License id
   */
  private $id;
  /**
   * @var string $shortname
   * License's short name
   */
  private $shortname;
  /**
   * @var integer $occurence
   * License's occurrence count
   */
  private $occurence;
  /**
   * @var integer $unique
   * License's unique count
   */
  private $unique;
  /**
   * @var string $spdxName
   * License's spdx name
   */
  private $spdxName;

  /**
   * @param integer $id
   * @param string $shortname
   * @param integer $occurence
   * @param integer $unique
   * @param string $spdxName
   */
  public function __construct($id, $shortname, $occurence, $unique, $spdxName)
  {
    $this->id = $id;
    $this->shortname = $shortname;
    $this->occurence = $occurence;
    $this->unique = $unique;
    $this->spdxName = $spdxName;
  }

  /**
   * @return integer
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @return string
   */
  public function getShortname()
  {
    return $this->shortname;
  }

  /**
   * @return integer
   */
  public function getOccurence()
  {
    return $this->occurence;
  }

  /**
   * @return integer
   */
  public function getUnique()
  {
    return $this->unique;
  }

  /**
   * @return string
   */
  public function getSpdxName()
  {
    return $this->spdxName;
  }

  /**
   * JSON representation of current scannedLicense
   * @param integer $version
   * @return string
   */
  public function getJSON($version=ApiVersion::V1)
  {
    return json_encode($this->getArray($version));
  }

  /**
   * Get ScannedLicense element as associative array
   * @param integer $version
   * @return array
   */
  public function getArray($version=ApiVersion::V1)
  {
    if ($version == ApiVersion::V2) {
      return [
        'id' => $this->getId(),
        'shortName' => $this->getShortname(),
        'occurence' => $this->getOccurence(),
        'unique' => $this->getUnique(),
        'spdxName' => $this->getSpdxName()
      ];
    }
    return [
      'id' => $this->getId(),
      'shortname' => $this->getShortname(),
      'occurence' => $this->getOccurence(),
      'unique' => $this->getUnique(),
      'spdxName' => $this->getSpdxName()
    ];
  }

  /**
   * @param integer $id
   */
  public function setId($id)
  {
    $this->id = $id;
  }

  /**
   * @param string $shortname
   */
  public function setShortname($shortname)
  {
    $this->shortname = $shortname;
  }

  /**
   * @param integer $occurence
   */
  public function setOccurence($occurence)
  {
    $this->occurence = $occurence;
  }

  /**
   * @param integer $unique
   */
  public function setUnique($unique)
  {
    $this->unique = $unique;
  }

  /**
   * @param string $spdxName
   */
  public function setSpdxName($spdxName)
  {
    $this->spdxName = $spdxName;
  }
}
