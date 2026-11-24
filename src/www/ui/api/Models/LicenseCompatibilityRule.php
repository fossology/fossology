<?php
/*
 SPDX-FileCopyrightText: © 2026 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief LicenseCompatibilityRule model
 */
namespace Fossology\UI\Api\Models;

/**
 * @class LicenseCompatibilityRule
 * @brief Model to hold a single license compatibility rule
 */
class LicenseCompatibilityRule
{
  /**
   * @var int $id
   * Id of the rule
   */
  private $id;
  /**
   * @var int|null $firstLicenseId
   * Id of the first license of the rule
   */
  private $firstLicenseId;
  /**
   * @var string|null $firstLicenseName
   * Short name of the first license of the rule
   */
  private $firstLicenseName;
  /**
   * @var int|null $secondLicenseId
   * Id of the second license of the rule
   */
  private $secondLicenseId;
  /**
   * @var string|null $secondLicenseName
   * Short name of the second license of the rule
   */
  private $secondLicenseName;
  /**
   * @var string|null $firstType
   * License type of the first license of the rule
   */
  private $firstType;
  /**
   * @var string|null $secondType
   * License type of the second license of the rule
   */
  private $secondType;
  /**
   * @var string $comment
   * Description of the rule
   */
  private $comment;
  /**
   * @var boolean $compatibility
   * Compatibility result of the rule
   */
  private $compatibility;

  /**
   * @param int $id
   * @param int|null $firstLicenseId
   * @param string|null $firstLicenseName
   * @param int|null $secondLicenseId
   * @param string|null $secondLicenseName
   * @param string|null $firstType
   * @param string|null $secondType
   * @param string $comment
   * @param boolean $compatibility
   */
  public function __construct($id, $firstLicenseId, $firstLicenseName,
                              $secondLicenseId, $secondLicenseName, $firstType,
                              $secondType, $comment, $compatibility)
  {
    $this->id = $id;
    $this->firstLicenseId = $firstLicenseId;
    $this->firstLicenseName = $firstLicenseName;
    $this->secondLicenseId = $secondLicenseId;
    $this->secondLicenseName = $secondLicenseName;
    $this->firstType = $firstType;
    $this->secondType = $secondType;
    $this->comment = $comment;
    $this->compatibility = $compatibility;
  }

  /**
   * Create a new model object from a `license_rules` row.
   * @param array $row Row as returned by CompatibilityDao
   * @return LicenseCompatibilityRule
   */
  public static function fromArray($row)
  {
    return new LicenseCompatibilityRule(
      intval($row['lr_pk']),
      $row['first_rf_fk'] === null ? null : intval($row['first_rf_fk']),
      $row['first_rf_shortname'],
      $row['second_rf_fk'] === null ? null : intval($row['second_rf_fk']),
      $row['second_rf_shortname'],
      $row['first_type'],
      $row['second_type'],
      $row['comment'],
      $row['compatibility'] === 't'
    );
  }

  /**
   * @return int
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @return int|null
   */
  public function getFirstLicenseId()
  {
    return $this->firstLicenseId;
  }

  /**
   * @return string|null
   */
  public function getFirstLicenseName()
  {
    return $this->firstLicenseName;
  }

  /**
   * @return int|null
   */
  public function getSecondLicenseId()
  {
    return $this->secondLicenseId;
  }

  /**
   * @return string|null
   */
  public function getSecondLicenseName()
  {
    return $this->secondLicenseName;
  }

  /**
   * @return string|null
   */
  public function getFirstType()
  {
    return $this->firstType;
  }

  /**
   * @return string|null
   */
  public function getSecondType()
  {
    return $this->secondType;
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
  public function getCompatibility()
  {
    return $this->compatibility;
  }

  /**
   * @param int $id
   */
  public function setId($id)
  {
    $this->id = $id;
  }

  /**
   * @param int|null $firstLicenseId
   */
  public function setFirstLicenseId($firstLicenseId)
  {
    $this->firstLicenseId = $firstLicenseId;
  }

  /**
   * @param string|null $firstLicenseName
   */
  public function setFirstLicenseName($firstLicenseName)
  {
    $this->firstLicenseName = $firstLicenseName;
  }

  /**
   * @param int|null $secondLicenseId
   */
  public function setSecondLicenseId($secondLicenseId)
  {
    $this->secondLicenseId = $secondLicenseId;
  }

  /**
   * @param string|null $secondLicenseName
   */
  public function setSecondLicenseName($secondLicenseName)
  {
    $this->secondLicenseName = $secondLicenseName;
  }

  /**
   * @param string|null $firstType
   */
  public function setFirstType($firstType)
  {
    $this->firstType = $firstType;
  }

  /**
   * @param string|null $secondType
   */
  public function setSecondType($secondType)
  {
    $this->secondType = $secondType;
  }

  /**
   * @param string $comment
   */
  public function setComment($comment)
  {
    $this->comment = $comment;
  }

  /**
   * @param boolean $compatibility
   */
  public function setCompatibility($compatibility)
  {
    $this->compatibility = $compatibility;
  }

  /**
   * JSON representation of the current rule
   * @param integer $version
   * @return string
   */
  public function getJSON($version = ApiVersion::V1)
  {
    return json_encode($this->getArray($version));
  }

  /**
   * Get the rule as an associative array
   * @param integer $version
   * @return array
   */
  public function getArray($version = ApiVersion::V1)
  {
    if ($version == ApiVersion::V2) {
      return [
        'id' => $this->getId(),
        'firstLicenseId' => $this->getFirstLicenseId(),
        'firstLicenseName' => $this->getFirstLicenseName(),
        'secondLicenseId' => $this->getSecondLicenseId(),
        'secondLicenseName' => $this->getSecondLicenseName(),
        'firstType' => $this->getFirstType(),
        'secondType' => $this->getSecondType(),
        'comment' => $this->getComment(),
        'compatibility' => $this->getCompatibility()
      ];
    }
    return [
      'id' => $this->getId(),
      'first_license_id' => $this->getFirstLicenseId(),
      'first_license_name' => $this->getFirstLicenseName(),
      'second_license_id' => $this->getSecondLicenseId(),
      'second_license_name' => $this->getSecondLicenseName(),
      'first_type' => $this->getFirstType(),
      'second_type' => $this->getSecondType(),
      'comment' => $this->getComment(),
      'compatibility' => $this->getCompatibility()
    ];
  }
}
