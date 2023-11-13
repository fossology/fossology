<?php
/*
 SPDX-FileCopyrightText: © 2023 Divij Sharma <divijs75@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief SuccessfulAgent model
 */
namespace Fossology\UI\Api\Models;


class Permissions
{
  /**
   * @var string $publicPerm
   * public permissions
   */
  private $publicPerm;
  /**
   * @var array $permGroups
   * permissions for groups
   */
  private $permGroups;
  /**
   * @param string $PublicPerm
   * @param array $permGroups
   */
  public function __construct($publicPerm, $permGroups)
  {
    $this->publicPerm = $publicPerm;
    $this->permGroups = $permGroups;
  }

  /**
   * @return string
   */
  public function getpublicPerm()
  {
    return $this->publicPerm;
  }

  /**
   * @return array
   */
  public function getpermGroups()
  {
    return $this->permGroups;
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
          'publicPerm' => $this->getpublicPerm(),
          'permGroups' => $this->getpermGroups(),
        ];
    }
    return [
      'publicPerm' => $this->getpublicPerm(),
      'permGroups' => $this->getpermGroups(),
    ];
  }

  /**
   * @param string $publicPerm
   */
  public function setpublicPerm($publicPerm)
  {
    $this->publicPerm= $publicPerm;
  }

  /**
   * @param array $permGroups
   */
  public function setpermGroups($permGroups)
  {
    $this->permGroups = $permGroups;
  }
}