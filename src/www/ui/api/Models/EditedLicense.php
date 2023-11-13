<?php
/*
 SPDX-FileCopyrightText: © 2023 Akash Kumar Sah <akashsah2003@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief EditedLicense model
 */
namespace Fossology\UI\Api\Models;

class EditedLicense
{
  /**
   * @var integer $id
   * License id
   */
  private $id;
  /**
   * @var string $shortName
   * License's short name
   */
  private $shortName;
  /**
   * @var integer $count
   * License's count
   */
  private $count;
  /**
   * @var string $spdxId
   * License's spdx id
   */
  private $spdxId;

  /**
   * @param integer $id
   * @param string $shortName
   * @param integer $count
   * @param string $spdxId
   */
  public function __construct($id, $shortName, $count, $spdxId)
  {
    $this->id = $id;
    $this->shortName = $shortName;
    $this->count = $count;
    $this->spdxId = $spdxId;
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
  public function getShortName()
  {
    return $this->shortName;
  }

  /**
   * @return integer
   */
  public function getCount()
  {
    return $this->count;
  }

  /**
   * @return string
   */
  public function getSpdxId()
  {
    return $this->spdxId;
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
          'shortName' => $this->getShortName(),
          'count' => $this->getCount(),
          'spdxId' => $this->getSpdxId()
        ];
    }
    return [
      'id' => $this->getId(),
      'shortName' => $this->getShortName(),
      'count' => $this->getCount(),
      'spdx_id' => $this->getSpdxId()
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
   * @param string $shortName
   */
  public function setShortName($shortName)
  {
    $this->shortName = $shortName;
  }

  /**
   * @param integer $count
   */
  public function setCount($count)
  {
    $this->count = $count;
  }

  /**
   * @param string $spdxName
   */
  public function setSpdxId($spdxId)
  {
    $this->spdxId = $spdxId;
  }
}
