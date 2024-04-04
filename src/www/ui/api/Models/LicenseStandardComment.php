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


class LicenseStandardComment
{
  /**
   * @var int $id
   * Id for comment
   */
  private $id;
  /**
   * @var string $name
   * Comment name
   */
  private $name;
  /**
   * @var string $comment
   * Comment text
   */
  private $comment;
  /**
   * @var boolean $isEnabled
   * Comment is enabled or not
   */
  private $isEnabled;

  /**
   * @param int $id
   * @param string $name
   * @param string $comment
   * @param boolean $isEnabled
   */
  public function __construct($id, $name, $comment, $isEnabled)
  {
    $this->id = $id;
    $this->name = $name;
    $this->comment = $comment;
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
  public function getComment()
  {
    return $this->comment;
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
          'comment' => $this->getComment(),
          'isEnabled' => $this->getIsEnabled()
        ];
    }
    return [
      'id' => $this->getId(),
      'name' => $this->getName(),
      'comment' => $this->getComment(),
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
   * @param string $comment
   */
  public function setComment($comment)
  {
    $this->comment = $comment;
  }

  /**
   * @param boolean $isEnabled
   */
  public function setIsEnabled($isEnabled)
  {
    $this->isEnabled = $isEnabled;
  }
}
