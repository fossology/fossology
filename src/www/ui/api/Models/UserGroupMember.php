<?php
/*
 * SPDX-FileCopyrightText: © 2022 Samuel Dushimimana <dushsam100@gmail.com>
 *
 * SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief UserGroupMember model
 */

namespace Fossology\UI\Api\Models;

class UserGroupMember
{

  /**
   * @var User $user
   */
  private $user;

  /**
   * @var int $group_perm
   */
  private $group_perm;

  /**
   * UserGroupMember constructor.
   *
   * @param User $user
   * @param number $group_perm
   */
  public function __construct($user, $group_perm)
  {
    $this->user = $user;
    $this->group_perm = intval($group_perm);
  }

  ////// Setters //////

  /**
   * @param User $user
   */
  public function setUser($user)
  {
    $this->user = $user;
  }

  /**
   * @param number $group_perm
   */
  public function setGroupPerm($group_perm)
  {
    $this->group_perm = intval($group_perm);
  }


  ////// Getters //////

  /**
   * @return User
   */
  public function getUser()
  {
    return $this->user;
  }

  /**
   * @return number
   */
  public function getGroupPerm()
  {
    return $this->group_perm;
  }

  /**
   * @return string json
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  /**
   * Get the file element as associative array
   *
   * @return array
   */
  public function getArray($version=ApiVersion::V1)
  {
    if ($version==ApiVersion::V2) {
      return [
        'user' => $this->user->getArray($version),
        'groupPerm' => intval($this->group_perm)
      ];
    } else {
      return [
        'user' => $this->user->getArray($version),
        'group_perm' => intval($this->group_perm)
      ];
    }
  }
}
