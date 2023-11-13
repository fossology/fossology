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


class GroupPermission
{
  /**
   * @var string $perm
   * Permissions
   */
  private $perm;
  /**
   * @var string $groupPk
   * Group id
   */
  private $groupPk;
  /**
   * @var string $groupName
   * Group name
   */
  private $groupName;

  /**
   * @param string $perm
   * @param string $groupPk
   * @param string $groupName
   */
  public function __construct($perm, $groupPk, $groupName)
  {
    $this->perm = $perm;
    $this->groupPk = $groupPk;
    $this->groupName = $groupName;
  }

  /**
   * @return string
   */
  public function getPerm()
  {
    return $this->perm;
  }

  /**
   * @return string
   */
  public function getGroupPk()
  {
    return $this->groupPk;
  }

  /**
   * @return string
   */
  public function getGroupName()
  {
    return $this->groupName;
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
          'perm' => $this->getPerm(),
          'groupPk' => $this->getGroupPk(),
          'groupName' => $this->getGroupName()
        ];
    }
    return [
      'perm' => $this->getPerm(),
      'group_pk' => $this->getGroupPk(),
      'group_name' => $this->getGroupName()
    ];
  }

  /**
   * @param string $perm
   */
  public function setperm($perm)
  {
    $this->perm = $perm;
  }

  /**
   * @param string $groupPk
   */
  public function setGroupPk($groupPk)
  {
    $this->groupPk = $groupPk;
  }

  /**
   * @param string $groupName
   */
  public function setGroupName($groupName)
  {
    $this->groupName = $groupName;
  }
}