<?php
/*
 SPDX-FileCopyrightText: © 2023 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Model to hold candidate license information
 */

namespace Fossology\UI\Api\Models;

use Fossology\UI\Api\Models\ApiVersion;

class LicenseCandidate
{
  /**
   * @var int $id
   * License id
   */
  private $id;

  /**
   * @var string $shortname
   * License shortname
   */
  private $shortname;

  /**
   * @var string|null $spdxid
   * License SPDX ID
   */
  private $spdxid;

  /**
   * @var string|null $fullname
   * License full name
   */
  private $fullname;

  /**
   * @var string|null $text
   * License text
   */
  private $text;

  /**
   * @var string $group_name
   * Group name in which the candidate was created
   */
  private $group_name;

  /**
   * @var int $group_id
   * Group ID in which the candidate was created
   */
  private $group_id;

  /**
   * @param int $id
   * @param string $shortname
   * @param string|null $spdxid
   * @param string|null $fullname
   * @param string|null $text
   * @param string $group_name
   * @param int $group_id
   */
  public function __construct($id, $shortname, $spdxid, $fullname, $text,
                              $group_name, $group_id)
  {
    $this->setId($id);
    $this->setShortname($shortname);
    $this->setSpdxId($spdxid);
    $this->setFullname($fullname);
    $this->setText($text);
    $this->setGroupName($group_name);
    $this->setGroupId($group_id);
  }

  /**
   * @return int
   */
  public function getId(): int
  {
    return $this->id;
  }

  /**
   * @param int $id
   */
  public function setId($id): void
  {
    $this->id = $id;
  }

  /**
   * @return string
   */
  public function getShortname(): string
  {
    return $this->shortname;
  }

  /**
   * @param string $shortname
   */
  public function setShortname($shortname): void
  {
    $this->shortname = $shortname;
  }

  /**
   * @return string|null
   */
  public function getSpdxid()
  {
    return $this->spdxid;
  }

  /**
   * @param string|null $spdxid
   */
  public function setSpdxid($spdxid): void
  {
    $this->spdxid = $spdxid;
  }

  /**
   * @return string|null
   */
  public function getFullname()
  {
    return $this->fullname;
  }

  /**
   * @param string|null $fullname
   */
  public function setFullname($fullname): void
  {
    $this->fullname = $fullname;
  }

  /**
   * @return string|null
   */
  public function getText()
  {
    return $this->text;
  }

  /**
   * @param string|null $text
   */
  public function setText($text): void
  {
    $this->text = $text;
  }

  /**
   * @return string
   */
  public function getGroupName(): string
  {
    return $this->group_name;
  }

  /**
   * @param string $group_name
   */
  public function setGroupName($group_name): void
  {
    $this->group_name = $group_name;
  }

  /**
   * @return int
   */
  public function getGroupId(): int
  {
    return $this->group_id;
  }

  /**
   * @param int $group_id
   */
  public function setGroupId($group_id): void
  {
    $this->group_id = $group_id;
  }

  public function getArray($apiVersion = ApiVersion::V1)
  {
    if ($apiVersion == ApiVersion::V2) {
      return [
        "id"         => $this->getId(),
        "shortname"  => $this->getShortname(),
        "spdxid"     => $this->getSpdxid(),
        "fullname"   => $this->getFullname(),
        "text"       => $this->getText(),
        "groupName" => $this->getGroupName(),
        "groupId"   => $this->getGroupId()
      ];
    } else {
      return [
        "id"         => $this->getId(),
        "shortname"  => $this->getShortname(),
        "spdxid"     => $this->getSpdxid(),
        "fullname"   => $this->getFullname(),
        "text"       => $this->getText(),
        "group_name" => $this->getGroupName(),
        "group_id"   => $this->getGroupId()
      ];
    }
  }

  /**
   * Create new object from database row
   * @param array $licenseData Row from database
   * @return LicenseCandidate
   */
  public static function createFromArray($licenseData)
  {
    return new LicenseCandidate($licenseData['rf_pk'],
      $licenseData['rf_shortname'], $licenseData['rf_spdx_id'],
      $licenseData['rf_fullname'], $licenseData['rf_text'],
      $licenseData['group_name'], $licenseData['group_pk']);
  }

  /**
   * Convert array of results from database.
   *
   * @param array $rows Rows from database
   * @return array
   */
  public static function convertDbArray($rows , $version = ApiVersion::V1)
  {
    $candidates = [];
    if (empty($rows)) {
      return $candidates;
    }
    foreach ($rows as $row) {
      $candidates[] = self::createFromArray($row)->getArray($version);
    }
    return $candidates;
  }
}
