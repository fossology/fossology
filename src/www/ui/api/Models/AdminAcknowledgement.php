<?php
/*
 SPDX-FileCopyrightText: © 2024 Divij Sharma <divijs75@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief SuccessfulAgent model
 */
namespace Fossology\UI\Api\Models;


class AdminAcknowledgement
{
  /**
   * @var int $id
   * Id for admin acknowledgement
   */
  private $id;
  /**
   * @var string $name
   * Admin acknowledgement name
   */
  private $name;
  /**
   * @var string $acknowledgement
   * Acknowledgement text
   */
  private $acknowledgement;
  /**
   * @var boolean $isEnabled
   * Admin acknowledgement is enabled or not
   */
  private $isEnabled;

  /**
   * @param int $id
   * @param string $name
   * @param string $acknowledgement
   * @param boolean $isEnabled
   */
  public function __construct($id, $name, $acknowledgement, $isEnabled)
  {
    $this->id = $id;
    $this->name = $name;
    $this->acknowledgement = $acknowledgement;
    $this->isEnabled = $isEnabled;
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
  public function getName()
  {
    return $this->name;
  }

  /**
   * @return string
   */
  public function getAcknowledgement()
  {
    return $this->acknowledgement;
  }

  /**
   * @return boolean
   */
  public function getIsEnabled()
  {
    return $this->isEnabled;
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
          'name' => $this->getName(),
          'acknowledgement' => $this->getAcknowledgement(),
          'isEnabled' => $this->getIsEnabled()
        ];
    }
    return [
      'id' => $this->getId(),
      'name' => $this->getName(),
      'acknowledgement' => $this->getAcknowledgement(),
      'is_enabled' => $this->getIsEnabled()
    ];
  }

  /**
   * @param int $id
   */
  public function setId($id)
  {
    $this->id = $id;
  }

  /**
   * @param string $name
   */
  public function setName($name)
  {
    $this->name = $name;
  }

  /**
   * @param string $acknowledgement
   */
  public function setAcknowledgement($acknowledgement)
  {
    $this->acknowledgement = $acknowledgement;
  }

  /**
   * @param boolean $isEnabled
   */
  public function setIsEnabled($isEnabled)
  {
    $this->isEnabled = $isEnabled;
  }
}
